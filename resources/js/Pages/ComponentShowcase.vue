<template>
    <Head title="Component Showcase" />
    <div>
        <h1 class="page-title">Component Showcase</h1>

        <div class="form-card">
            <div class="form-card-header"><h3>Badge</h3></div>
            <div class="form-card-body">
                <div class="btn-section">
                    <div class="btn-section-label">Renkler (filled)</div>
                    <div class="btn-row">
                        <Badge v-for="c in colors" :key="c" :color="c" :label="c" />
                    </div>
                </div>
                <div class="btn-section">
                    <div class="btn-section-label">Varyantlar</div>
                    <div class="btn-row" style="align-items: center">
                        <Badge color="primary" variant="filled" label="Filled" />
                        <Badge color="primary" variant="tonal" label="Tonal" />
                        <Badge color="primary" variant="outlined" label="Outlined" />
                        <Badge color="danger" :count="7" />
                        <Badge color="success" dot />
                        <Badge color="primary" :icon="Star" label="İkonlu" />
                    </div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>Tag</h3></div>
            <div class="form-card-body">
                <div class="btn-row">
                    <Tag v-for="c in colors" :key="c" :color="c" :label="c" />
                    <Tag color="primary" label="Kapatılabilir" closable @close="showToast({ type: 'info', title: 'Tag kapatıldı' })" />
                    <Tag color="info" label="Durum" status />
                    <Tag color="success" label="Sayaç" :count="4" />
                    <Tag color="primary" variant="outlined" label="Outlined" />
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>Avatar</h3></div>
            <div class="form-card-body">
                <div class="btn-row" style="align-items: center">
                    <Avatar v-for="s in ['sm', 'md', 'lg', 'xl']" :key="s" :size="s" initials="ÖK" />
                    <Avatar color="success" initials="AB" status="online" />
                    <Avatar color="warning" initials="CD" status="away" />
                    <Avatar color="danger" initials="EF" status="busy" />
                    <Avatar color="neutral" initials="GH" status="offline" />
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>Alert</h3></div>
            <div class="form-card-body" style="display: flex; flex-direction: column; gap: 10px">
                <Alert variant="info" title="Bilgi" message="Bu bir bilgilendirme mesajıdır." />
                <Alert variant="success" title="Başarılı" message="İşlem başarıyla tamamlandı." closable />
                <Alert variant="warning" title="Uyarı" message="Bu işlem geri alınamaz." />
                <Alert variant="error" title="Hata" message="Bir hata oluştu, lütfen tekrar deneyin." closable />
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>Tooltip</h3></div>
            <div class="form-card-body">
                <div class="btn-row" style="gap: 32px; padding: 20px 0">
                    <Tooltip text="Üstte tooltip" position="top">
                        <button class="btn btn-outline-primary">Top</button>
                    </Tooltip>
                    <Tooltip text="Altta tooltip" position="bottom" variant="light">
                        <button class="btn btn-outline-primary">Bottom (light)</button>
                    </Tooltip>
                    <Tooltip text="Solda" position="left">
                        <button class="btn btn-outline-primary">Left</button>
                    </Tooltip>
                    <Tooltip text="Sağda" position="right">
                        <button class="btn btn-outline-primary">Right</button>
                    </Tooltip>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>Divider</h3></div>
            <div class="form-card-body" style="display: flex; flex-direction: column; gap: 16px">
                <Divider />
                <Divider variant="dashed" />
                <Divider label="VEYA" />
                <Divider :icon="Star" />
                <div style="display: flex; align-items: center; gap: 12px; height: 24px">
                    <span>Sol</span>
                    <Divider vertical />
                    <span>Sağ</span>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>ProgressBar</h3></div>
            <div class="form-card-body" style="display: flex; flex-direction: column; gap: 14px">
                <ProgressBar :value="35" label="Standart" show-value />
                <ProgressBar :value="78" color="warning" label="Uyarı eşiği" show-value />
                <ProgressBar :value="92" color="danger" label="Kritik" show-value />
                <ProgressBar :value="60" color="success" height="md" striped />
                <ProgressBar indeterminate color="info" />
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>Spinner</h3></div>
            <div class="form-card-body">
                <div class="btn-row" style="align-items: center">
                    <Spinner v-for="s in ['sm', 'md', 'lg']" :key="s" :size="s" />
                    <Spinner variant="dots" color="primary" />
                    <Spinner variant="gradient" color="danger" size="lg" />
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>Tabs</h3></div>
            <div class="form-card-body" style="display: flex; flex-direction: column; gap: 20px">
                <Tabs v-model="activeTab" :tabs="tabItems" variant="underline" />
                <Tabs v-model="activeTab" :tabs="tabItems" variant="pills" />
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>Accordion</h3></div>
            <div class="form-card-body">
                <Accordion :items="accordionItems" />
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>StarRating</h3></div>
            <div class="form-card-body">
                <div class="btn-row" style="align-items: center">
                    <StarRating v-model="starValue" />
                    <span style="font-size: 12px; color: #888">Seçilen: {{ starValue }}</span>
                    <StarRating :model-value="3.5" readonly size="sm" />
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>Skeleton</h3></div>
            <div class="form-card-body" style="display: flex; align-items: center; gap: 12px">
                <Skeleton variant="circle" width="42px" height="42px" />
                <div style="flex: 1; display: flex; flex-direction: column; gap: 8px">
                    <Skeleton width="60%" />
                    <Skeleton width="90%" />
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>Toggle</h3></div>
            <div class="form-card-body">
                <div class="btn-row" style="align-items: center">
                    <Toggle v-model="toggleValue" label="Bildirimler" />
                    <Toggle :model-value="true" label="Devre dışı (açık)" disabled />
                    <Toggle :model-value="false" label="Devre dışı (kapalı)" disabled />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Star } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from '@/composables/useToast.js';
import Badge from '@/Components/Badge.vue';
import Tag from '@/Components/Tag.vue';
import Avatar from '@/Components/Avatar.vue';
import Alert from '@/Components/Alert.vue';
import Tooltip from '@/Components/Tooltip.vue';
import Divider from '@/Components/Divider.vue';
import ProgressBar from '@/Components/ProgressBar.vue';
import Spinner from '@/Components/Spinner.vue';
import Tabs from '@/Components/Tabs.vue';
import Accordion from '@/Components/Accordion.vue';
import StarRating from '@/Components/StarRating.vue';
import Skeleton from '@/Components/Skeleton.vue';
import Toggle from '@/Components/Toggle.vue';

defineOptions({ layout: AppLayout });

const { showToast } = useToast();

const colors = ['primary', 'success', 'warning', 'danger', 'info', 'neutral'];

const activeTab = ref('overview');
const tabItems = [
    { key: 'overview', label: 'Genel Bakış' },
    { key: 'details', label: 'Detaylar', count: 3 },
    { key: 'disabled', label: 'Kapalı', disabled: true },
];

const accordionItems = [
    { id: 1, title: 'Bölüm 1', content: 'İlk bölümün içeriği burada yer alır.' },
    { id: 2, title: 'Bölüm 2', content: 'İkinci bölümün içeriği burada yer alır.' },
    { id: 3, title: 'Bölüm 3', content: 'Üçüncü bölümün içeriği burada yer alır.' },
];

const starValue = ref(4);
const toggleValue = ref(true);
</script>

<style scoped>
.page-title {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 16px;
}

.form-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #ebebf0;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    margin-bottom: 14px;
    overflow: visible;
}
.form-card-header {
    padding: 14px 18px;
    border-bottom: 1px solid #f0f0f5;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.form-card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.form-card-body { padding: 18px; }

.btn-row { display: flex; flex-wrap: wrap; gap: 10px; }
.btn-section { margin-bottom: 20px; }
.btn-section:last-child { margin-bottom: 0; }
.btn-section-label {
    font-size: 10.5px;
    font-weight: 700;
    color: #aaa;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 8px;
}
</style>
