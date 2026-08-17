<?php

use Deptrac\Deptrac\Contract\Config\Collector\BoolConfig;
use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    // Creative'in AI infra alt-katmanı: Product/Atelier bu katmana bağımlı olabilir
    // (paylaşılan AI client/contract), ama Creative'in domain katmanına asla.
    // Not: deptrac dosyaları MUTLAK yol ile tarıyor (ör. "/home/.../Modules/Product/...")
    // — bu yüzden desenler "^" ile ANKORLANMAZ, proje kökünden bağımsız olarak eşleşir.
    //
    // AI infra "yüzeyi": Contracts/Drivers (asıl istemci/kontrat) + doğrudan Services/Ai/
    // altındaki *Request değer nesneleri ve Support/ yardımcıları — bunlar contract
    // imzalarının parçası (ör. CaptionGeneratorContract::generate(CaptionRequest $r)),
    // AiSceneService gibi orkestrasyon Service'leri DEĞİL (onlar Domain'de kalır).
    // Not: DirectoryConfig::create() kendi escaping'i içinde tek "\" karakterlerini "\\"
    // yapıyor; bu yüzden burada regex escape'i ("\.") KULLANILMAZ, çıplak "." yeterli.
    $aiInfraPattern = 'Modules/Creative/Services/Ai/((Contracts|Drivers|Support)/|[A-Za-z]+Request.php$)';

    $creativeAiInfra = Layer::withName('Modules_Creative_AiInfra')->collectors(
        DirectoryConfig::create($aiInfraPattern),
    );

    $creativeDomain = Layer::withName('Modules_Creative_Domain')->collectors(
        BoolConfig::create(
            must: [DirectoryConfig::create('Modules/Creative/')],
            mustNot: [DirectoryConfig::create($aiInfraPattern)],
        ),
    );

    // app/ Laravel'in temel iskeleti (base Controller, User modeli, ortak Support) —
    // her modülün üzerine oturduğu bir "kernel" katmanı; tüm modüller buna bağımlı olabilir.
    $app = Layer::withName('App')->collectors(DirectoryConfig::create('/app/'));
    $atelier = Layer::withName('Modules_Atelier')->collectors(DirectoryConfig::create('Modules/Atelier/'));
    $bagisto = Layer::withName('Modules_Bagisto')->collectors(DirectoryConfig::create('Modules/Bagisto/'));
    $finance = Layer::withName('Modules_Finance')->collectors(DirectoryConfig::create('Modules/Finance/'));
    $marketplace = Layer::withName('Modules_Marketplace')->collectors(DirectoryConfig::create('Modules/Marketplace/'));
    $product = Layer::withName('Modules_Product')->collectors(DirectoryConfig::create('Modules/Product/'));
    $superadmin = Layer::withName('Modules_Superadmin')->collectors(DirectoryConfig::create('Modules/Superadmin/'));
    $tenant = Layer::withName('Modules_Tenant')->collectors(DirectoryConfig::create('Modules/Tenant/'));

    $config
        ->paths('app', 'Modules')
        ->excludeFiles('#.*/(Tests|tests)/.*#')
        ->layers(
            $app,
            $atelier,
            $bagisto,
            $creativeAiInfra,
            $creativeDomain,
            $finance,
            $marketplace,
            $product,
            $superadmin,
            $tenant,
        )
        ->rulesets(
            // App, Laravel'in kompozisyon kökü — tüm modüllere bağımlı olabilir (base
            // Controller/User modeli, HandleInertiaRequests gibi cross-cutting wiring).
            Ruleset::forLayer($app)->accesses(
                $atelier, $bagisto, $creativeAiInfra, $creativeDomain, $finance, $marketplace,
                $product, $superadmin, $tenant,
            ),
            // Marketplace ve AI infra katmanı tamamen bağımsız — hiçbir şeye bağımlı olamaz
            // (App dahil — mevcut kodda da hiç kullanmıyorlar, bu yüzden istisna eklenmedi).
            Ruleset::forLayer($marketplace),
            // Bagisto: Modules/Marketplace pattern'inden kasıtlı olarak bağımsız, ayrı bir
            // senkron modülü (bkz. Product event'lerini dinler, Marketplace contract/DTO'larını
            // kullanmaz). Product'ın domain event'lerine/modeline VE Tenant'ın domain
            // event'lerine/modeline (owner ilişkisi dahil) bağımlı — başka bir şeye değil.
            Ruleset::forLayer($bagisto)->accesses($product, $tenant, $app),
            Ruleset::forLayer($creativeAiInfra),
            Ruleset::forLayer($creativeDomain)->accesses($creativeAiInfra, $product, $app),
            Ruleset::forLayer($atelier)->accesses($product, $creativeAiInfra, $app),
            Ruleset::forLayer($tenant)->accesses($marketplace, $product, $app),
            Ruleset::forLayer($superadmin)->accesses($tenant, $app),
            Ruleset::forLayer($finance)->accesses($product, $tenant, $atelier, $app),
            // Product'ın Tenant/Atelier'e mevcut bağımlılığı BİLİNÇLİ OLARAK burada yok —
            // bunlar deptrac.baseline.yaml'da donduruldu (mevcut borç), hedef kural değil.
            // Yeni bir Product -> Tenant/Atelier importu bu yüzden yeni bir ihlal olarak raporlanır.
            Ruleset::forLayer($product)->accesses($superadmin, $creativeAiInfra, $app),
        );

    if (is_file(__DIR__.'/deptrac.baseline.yaml')) {
        $config->baseline(__DIR__.'/deptrac.baseline.yaml');
    }
};
