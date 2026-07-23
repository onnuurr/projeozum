Ancak ben tek seferde "Architecture Doctor" yazdırmazdım. Yaklaşık 8-10 bin satırlık bir geliştirmeyi Claude'a tek seferde yaptırmak yerine dikey dilim (vertical slice) yaklaşımını kullanırdım.

Ben olsam şu sırayla ilerlerdim:

Faz 0 — Altyapı (en kritik)

Amaç: Rule Engine'in iskeletini oluşturmak.

Çıktılar:

artisan architecture:doctor
ArchitectureRule contract
RuleRunner
RuleResult
Finding
Policy
AnalysisReport
Rule auto-discovery
JSON/Text console output
--ci
--json

Henüz tek bir gerçek kural bile olmayabilir.

Bu fazın sonunda sistem çalışıyor olmalı.

Faz 1 — Deptrac entegrasyonu

Bundan sonra dependency analizi.

Çıktılar:

Deptrac config
module.json architecture metadata
Forbidden dependency rule
Circular dependency report

Bu faz sonunda modüller arası bağımlılıklar raporlanabilir.

Faz 2 — Migration Discipline

Bu proje için en değerli kısım.

Kurallar örneğin:

down() boş mu?
migration edit edilmiş mi?
rename yerine drop yapılmış mı?
normalize migration'ı PHP yerine SQL mi kullanıyor?
Prunable unutulmuş mu?
soft delete kuralı ihlal edilmiş mi?

Bunlar tamamen sizin projeye özel.

Faz 3 — AI Layer

Creative tarafındaki kuralları kontrol eder.

Örneğin:

GeminiClient bypass edilmiş mi?
Contract yerine concrete class inject edilmiş mi?
Mock driver unutulmuş mu?
PromptBuilder atlanmış mı?
Faz 4 — Controller Discipline

Kurallar:

Controller içinde DB::table()
Controller içinde transaction
Controller içinde iş kuralı
Controller içinde create/update
Controller çok büyük mü
Faz 5 — Tenant Isolation

Kurallar:

Product::all()
scopeAccessibleToTenant unutulmuş
tenant filtreleri eksik
cross-tenant erişim ihtimali
Faz 6 — Read-only Quality Rules

En son bunlar.

unused service
dead controller
orphan migration
duplicate helper
duplicate config
long method
god class

Hiçbiri build'i durdurmaz.

Bir önerim daha var

Bence bunu Modules/ArchitectureDoctor olarak yazmayın.

Ben olsam şöyle yaparım:

tools/

    architecture-doctor/

        app/

            Contracts/
            Engine/
            Rules/
            Report/
            Console/
            Policies/

artisan architecture:doctor

Sebebi:

Bu bir domain modülü değil.

Bu;

Product değil
Creative değil
Tenant değil
Marketplace değil

Bu tamamen development tooling.

Modül gibi görünmesi bile yanlış mesaj verir.

En önemlisi

Claude'a bundan sonra şu kuralı koyardım:

Yeni bir Rule yazılmadan önce mevcut Rule Engine değiştirilmeyecek.

Yani:

Yeni ihtiyaç → Yeni ArchitectureRule
Mevcut altyapıyı değiştirmek → İstisnai durum

Bu sayede 6 ay sonra 80-100 kural olsa bile çekirdek motor neredeyse hiç değişmez.

Bu mimarinin en büyük başarısı da bu olur: çekirdek sabit kalır, sistem yeni kurallarla büyür.