<?php

namespace Modules\Atelier\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Atelier\Models\Assignment;
use Modules\Atelier\Notifications\AssignmentNotification;

/**
 * Atölye iş akışı (Modül D): tasarım kartı/kalıp → kalıpçı/tasarımcı ataması →
 * durum takibi → dosya teslimi. Geçersiz durum geçişleri reddedilir.
 *
 * Akış: pending → in_progress → delivered → accepted | rejected(→ in_progress).
 */
class AssignmentService
{
    private const DIR = 'atelier/assignments';

    /**
     * @param  array<string,mixed>  $data
     */
    public function assign(array $data, ?int $assignedBy = null): Assignment
    {
        if (empty($data['design_card_id']) && empty($data['pattern_id'])) {
            throw new InvalidArgumentException('Atama için en az bir tasarım kartı veya kalıp seçilmelidir.');
        }

        $assignment = Assignment::create([
            'design_card_id' => $data['design_card_id'] ?? null,
            'pattern_id'     => $data['pattern_id'] ?? null,
            'kind'           => $data['kind'] ?? Assignment::KIND_PATTERN_MAKER,
            'assigned_to'    => $data['assigned_to'],
            'assigned_by'    => $assignedBy,
            'title'          => $data['title'],
            'instructions'   => $data['instructions'] ?? null,
            'due_date'       => $data['due_date'] ?? null,
            'status'         => Assignment::STATUS_PENDING,
        ]);

        // Atanan kişiye bildir.
        $assignment->assignee?->notify(AssignmentNotification::assigned($assignment));

        return $assignment;
    }

    /** Atanan kişi işe başlar. */
    public function start(Assignment $assignment): Assignment
    {
        $this->guard($assignment, [Assignment::STATUS_PENDING, Assignment::STATUS_REJECTED]);
        $assignment->update(['status' => Assignment::STATUS_IN_PROGRESS]);

        return $assignment->fresh();
    }

    /** Atanan kişi işi teslim eder (opsiyonel dosya ile). */
    public function deliver(Assignment $assignment, ?UploadedFile $file = null, ?string $note = null): Assignment
    {
        $this->guard($assignment, [Assignment::STATUS_IN_PROGRESS, Assignment::STATUS_PENDING]);

        $path = $assignment->delivered_file_path;
        if ($file instanceof UploadedFile) {
            $this->deleteFile($assignment->delivered_file_path);
            $path = $file->store(self::DIR, 'public');
        }

        $assignment->update([
            'status'              => Assignment::STATUS_DELIVERED,
            'delivered_file_path' => $path,
            'delivered_at'        => now(),
            'review_note'         => $note,
        ]);

        // Koordinatöre (atayan kişiye) bildir.
        $assignment->assigner?->notify(AssignmentNotification::delivered($assignment));

        return $assignment->fresh();
    }

    /** Koordinatör teslimi kabul eder. */
    public function accept(Assignment $assignment, ?string $note = null): Assignment
    {
        $this->guard($assignment, [Assignment::STATUS_DELIVERED]);
        $assignment->update([
            'status'      => Assignment::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'review_note' => $note ?? $assignment->review_note,
        ]);

        // Atanan kişiye sonucu bildir.
        $assignment->assignee?->notify(AssignmentNotification::reviewed($assignment, true));

        return $assignment->fresh();
    }

    /** Koordinatör reddeder; iş tekrar çalışmaya döner. */
    public function reject(Assignment $assignment, ?string $note = null): Assignment
    {
        $this->guard($assignment, [Assignment::STATUS_DELIVERED]);
        $assignment->update([
            'status'      => Assignment::STATUS_REJECTED,
            'review_note' => $note,
        ]);

        // Atanan kişiye sonucu bildir.
        $assignment->assignee?->notify(AssignmentNotification::reviewed($assignment, false));

        return $assignment->fresh();
    }

    /**
     * @param  array<int,string>  $allowed
     */
    private function guard(Assignment $assignment, array $allowed): void
    {
        if (! in_array($assignment->status, $allowed, true)) {
            throw new InvalidArgumentException(
                "Bu işlem '{$assignment->status}' durumundaki bir atamada yapılamaz."
            );
        }
    }

    private function deleteFile(?string $path): void
    {
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
