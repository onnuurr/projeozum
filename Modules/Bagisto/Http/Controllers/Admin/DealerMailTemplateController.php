<?php

namespace Modules\Bagisto\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Bagisto\Services\DealerMailContactService;
use Modules\Bagisto\Services\DealerMailTemplates;
use Modules\Bagisto\Services\DealerMailTemplateService;
use Modules\Bagisto\Services\ReturnMailTemplateService;
use Throwable;

/**
 * Bayi E-postaları: bayilere giden e-postaların (Başvuru, İade grupları) konu / giriş / alt paragraf
 * metinleri ve ortak destek-danışman kartı. Tasarım sabittir; önizleme ve test gönderimi kaydedilmemiş
 * taslakla örnek veri üzerinden yapılır.
 *
 * Her grup kendi yetkisine bağlıdır (DealerMailTemplates::permission()); kullanıcı yalnızca yetkili
 * olduğu grupları görür ve kaydeder; hiçbirine yetkisi yoksa 403 (bkz. allowedGroups()). İletişim kartı
 * ortaktır; herhangi bir grubun yetkisi olan düzenleyebilir.
 */
class DealerMailTemplateController extends Controller
{
    public function __construct(
        private DealerMailContactService $contact,
        private DealerMailTemplateService $applications,
        private ReturnMailTemplateService $returns,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Bagisto::DealerMailTemplates', [
            'groups' => collect($this->allowedGroups($request))
                ->map(fn (DealerMailTemplates $group) => [
                    'key' => $group->key(),
                    'label' => $group->label(),
                    'stages' => $this->stages($group),
                ])
                ->values(),
            'initialGroup' => $request->query('group'),
            'contact' => $this->contact->stored(),
            'contactFallback' => $this->contact->fallback(),
            'sender' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $groups = $this->allowedGroups($request);

        $data = $request->validate([
            'templates' => ['nullable', 'array'],
            ...collect($groups)->flatMap(fn (DealerMailTemplates $group) => [
                ...$this->templateRules("templates.{$group->key()}.*.", required: false),
                "templates.{$group->key()}.*.enabled" => ['required', 'boolean'],
            ])->all(),
            'contact' => ['nullable', 'array'],
            'contact.support_email' => ['nullable', 'email', 'max:191'],
            'contact.advisor_name' => ['nullable', 'string', 'max:100'],
            'contact.advisor_title' => ['nullable', 'string', 'max:100'],
            'contact.advisor_phone' => ['nullable', 'string', 'max:30'],
            'contact.advisor_whatsapp' => ['nullable', 'regex:/^[0-9]{10,15}$/'],
        ], [
            'contact.advisor_whatsapp.regex' => 'WhatsApp numarası yalnızca rakamlardan oluşmalı (ör. 905321234567).',
        ]);

        // Yetkisi olmayan grubun metinleri yok sayılır (doğrulanmadı, kaydedilmez).
        $updates = collect($groups)
            ->mapWithKeys(fn (DealerMailTemplates $group) => [
                $group->key() => collect($data['templates'][$group->key()] ?? [])->only(array_keys($group->catalog()))->all(),
            ])
            ->filter();

        $errors = $updates
            ->flatMap(fn (array $templates, string $key) => collect($templates)->flatMap(
                fn (array $template, string $stage) => $this->tagErrors($groups[$key], $stage, $template, "templates.{$key}.{$stage}.")
            ))
            ->all();

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $updates->each(fn (array $templates, string $key) => $groups[$key]->update($templates));

        if (array_key_exists('contact', $data)) {
            $this->contact->update($data['contact'] ?? []);
        }

        return back();
    }

    /** Kaydedilmemiş taslağın örnek veriyle işlenmiş hâli (sayfadaki önizleme). */
    public function preview(Request $request): JsonResponse
    {
        [$group, $stage, $template] = $this->draft($request);

        $mail = $group->previewMail($stage, $template, $request->user()->email);

        return response()->json([
            'subject' => $mail->envelope()->subject,
            'html' => $mail->render(),
        ]);
    }

    /** Taslağı örnek veriyle oturumdaki kullanıcının adresine hemen gönderir. */
    public function test(Request $request): JsonResponse
    {
        [$group, $stage, $template] = $this->draft($request);
        $email = $request->user()->email;

        try {
            Mail::to($email)->sendNow($group->previewMail($stage, $template, $email));
        } catch (Throwable $e) {
            Log::warning('Bayi e-postası test gönderimi başarısız', ['group' => $group->key(), 'stage' => $stage, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'E-posta gönderilemedi: '.$e->getMessage()], 422);
        }

        return response()->json(['message' => "Test e-postası {$email} adresine gönderildi."]);
    }

    /** @return array<string, DealerMailTemplates> grup anahtarı => grup, sayfadaki sırayla */
    private function groups(): array
    {
        return collect([$this->applications, $this->returns])
            ->keyBy(fn (DealerMailTemplates $group) => $group->key())
            ->all();
    }

    /** @return array<string, DealerMailTemplates> Kullanıcının yetkili olduğu gruplar; hiçbiri yoksa 403. */
    private function allowedGroups(Request $request): array
    {
        $groups = array_filter($this->groups(), fn (DealerMailTemplates $group) => $request->user()->can($group->permission()));

        abort_if($groups === [], 403);

        return $groups;
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function stages(DealerMailTemplates $group)
    {
        $values = $group->templates();

        return collect($group->catalog())
            ->map(fn (array $stage, string $key) => [
                'key' => $key,
                'label' => $stage['label'],
                'description' => $stage['description'],
                'defaults' => $stage['defaults'],
                'tags' => collect($group->tags($key))
                    ->map(fn (string $description, string $tag) => ['tag' => $tag, 'description' => $description])
                    ->values(),
                ...$values[$key],
            ])
            ->values();
    }

    /** @return array{0: DealerMailTemplates, 1: string, 2: array{subject: string, intro: string, body: ?string}} */
    private function draft(Request $request): array
    {
        $groups = $this->allowedGroups($request);

        $request->validate(['group' => ['required', Rule::in(array_keys($this->groups()))]]);
        abort_unless(isset($groups[$request->input('group')]), 403);

        $group = $groups[$request->input('group')];

        $data = $request->validate([
            'stage' => ['required', Rule::in(array_keys($group->catalog()))],
            ...$this->templateRules('template.'),
        ]);

        if ($errors = $this->tagErrors($group, $data['stage'], $data['template'], 'template.')) {
            throw ValidationException::withMessages($errors);
        }

        return [$group, $data['stage'], $data['template']];
    }

    /** @return array<string, array<int, string>> */
    private function templateRules(string $prefix, bool $required = true): array
    {
        return [
            rtrim($prefix, '.*') => [$required ? 'required' : 'nullable', 'array'],
            $prefix.'subject' => ['required', 'string', 'max:200'],
            $prefix.'intro' => ['required', 'string', 'max:2000'],
            $prefix.'body' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, string> alan => hata
     */
    private function tagErrors(DealerMailTemplates $group, string $stage, array $template, string $prefix): array
    {
        $errors = [];

        foreach (['subject', 'intro', 'body'] as $field) {
            $unknown = $group->unknownTags($stage, $template[$field] ?? null);

            if ($unknown) {
                $errors[$prefix.$field] = 'Bu adımda kullanılamayan etiket: '
                    .collect($unknown)->map(fn ($tag) => '{{'.$tag.'}}')->implode(', ');
            }
        }

        return $errors;
    }
}
