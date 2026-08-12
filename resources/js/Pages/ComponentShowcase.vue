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

        <div class="form-card">
            <div class="form-card-header"><h3>BarChart / LineChart</h3></div>
            <div class="form-card-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px">
                <BarChart :labels="weekLabels" :data="weekOrders" label="Siparişler" />
                <LineChart :labels="weekLabels" :data="weekRevenue" label="Gelir" />
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>PieChart / DoughnutChart</h3></div>
            <div class="form-card-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px">
                <PieChart :labels="categoryLabels" :data="categoryData" />
                <DoughnutChart :labels="categoryLabels" :data="categoryData" />
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>MiniChart / ChartCard</h3></div>
            <div class="form-card-body">
                <div class="btn-section">
                    <div class="btn-section-label">MiniChart (sparkline)</div>
                    <div style="display: flex; gap: 24px; align-items: center">
                        <div style="width: 120px"><MiniChart type="line" :data="sparkData" /></div>
                        <div style="width: 120px"><MiniChart type="bar" :data="sparkData" color="#16A34A" /></div>
                    </div>
                </div>
                <div class="btn-section" style="max-width: 320px">
                    <div class="btn-section-label">ChartCard</div>
                    <ChartCard title="Haftalık Gelir" value="₺48.250" change="+%12,4" change-trend="up" badge="7 gün" :labels="weekLabels" :data="weekRevenue" :height="120" />
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>StatWidget / StatusIndicator</h3></div>
            <div class="form-card-body">
                <div class="btn-section" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px">
                    <StatWidget :icon="ShoppingCart" value="1.284" title="Sipariş" change="+8,2%" trend="up" color="primary" />
                    <StatWidget :icon="Users" value="342" title="Yeni Müşteri" change="+3,1%" trend="up" color="success" />
                    <StatWidget :icon="PackageX" value="12" title="İade" change="-1,4%" trend="down" color="danger" />
                    <StatWidget :icon="Wallet" value="₺48.250" title="Gelir" loading />
                </div>
                <div class="btn-section">
                    <div class="btn-section-label">StatusIndicator</div>
                    <div class="btn-row">
                        <StatusIndicator status="online" />
                        <StatusIndicator status="away" />
                        <StatusIndicator status="busy" />
                        <StatusIndicator status="maintenance" />
                        <StatusIndicator status="error" />
                    </div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>ProgressWidget</h3></div>
            <div class="form-card-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px">
                <ProgressWidget title="Depo Doluluk" :value="72" :total="100" color="warning" description="1.440 / 2.000 birim" />
                <ProgressWidget title="Aylık Hedef" :value="48250" :total="60000" color="primary" description="Ocak ayı satış hedefi" />
            </div>
        </div>

        <div class="form-card" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0">
            <div style="padding: 18px; border-right: 1px solid #f0f0f5">
                <div class="btn-section-label" style="margin-bottom: 10px">NotificationWidget</div>
                <NotificationWidget :notifications="notifications" @dismiss="onDismissNotification" />
            </div>
            <div style="padding: 18px">
                <div class="btn-section-label" style="margin-bottom: 10px">UserProfileWidget</div>
                <UserProfileWidget name="Onur Karaosmanoğlu" email="onur@ozumserver.com.tr" initials="OK" role="Admin" online />
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>QuickActionWidget</h3></div>
            <div class="form-card-body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px">
                <QuickActionWidget :icon="PlusCircle" title="Yeni Sipariş" subtitle="Manuel oluştur" color="primary" />
                <QuickActionWidget :icon="FileDown" title="Rapor Al" subtitle="Excel dışa aktar" color="success" />
                <QuickActionWidget :icon="Truck" title="Kargo Takip" subtitle="Son hareketler" color="info" />
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>MiniChartWidget</h3></div>
            <div class="form-card-body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px">
                <MiniChartWidget title="Ziyaretçi" value="8.412" change="Son 7 gün" :data="sparkData" color="primary" />
                <MiniChartWidget title="Dönüşüm" value="%3,8" change="Son 7 gün" type="bar" :data="sparkData" color="success" />
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>FilterSection</h3></div>
            <div class="form-card-body">
                <FilterSection v-model="filterOpen" v-model:search="filterSearch" @apply="onFilterApply" @clear="onFilterClear">
                    <template #actions>
                        <button type="button" class="bg-primary text-white px-3 py-1.5 rounded text-xs font-medium">+ Yeni</button>
                    </template>
                    <div>
                        <label class="text-xs text-muted block mb-1">Kategori</label>
                        <select class="w-full px-2.5 py-1.5 text-xs border border-line rounded bg-white">
                            <option>Tüm Kategoriler</option>
                            <option>Kumaş</option>
                            <option>Aksesuar</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-muted block mb-1">Durum</label>
                        <select class="w-full px-2.5 py-1.5 text-xs border border-line rounded bg-white">
                            <option>Tümü</option>
                            <option>Aktif</option>
                            <option>Pasif</option>
                        </select>
                    </div>
                </FilterSection>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header"><h3>Offcanvas</h3></div>
            <div class="form-card-body">
                <button type="button" class="btn btn-outline-primary" @click="offcanvasOpen = true">Paneli Aç</button>
                <Offcanvas v-model="offcanvasOpen" title="Filtre Detayları">
                    <p style="font-size: 13px; color: #666">Bu panel içeriği tamamen slot ile beslenir.</p>
                    <template #footer>
                        <button type="button" class="btn btn-primary" style="width: 100%" @click="offcanvasOpen = false">Uygula</button>
                    </template>
                </Offcanvas>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Star, ShoppingCart, Users, PackageX, Wallet, PlusCircle, FileDown, Truck } from 'lucide-vue-next';
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
import BarChart from '@/Components/BarChart.vue';
import LineChart from '@/Components/LineChart.vue';
import PieChart from '@/Components/PieChart.vue';
import DoughnutChart from '@/Components/DoughnutChart.vue';
import MiniChart from '@/Components/MiniChart.vue';
import ChartCard from '@/Components/ChartCard.vue';
import StatWidget from '@/Components/StatWidget.vue';
import StatusIndicator from '@/Components/StatusIndicator.vue';
import ProgressWidget from '@/Components/ProgressWidget.vue';
import NotificationWidget from '@/Components/NotificationWidget.vue';
import QuickActionWidget from '@/Components/QuickActionWidget.vue';
import UserProfileWidget from '@/Components/UserProfileWidget.vue';
import MiniChartWidget from '@/Components/MiniChartWidget.vue';
import FilterSection from '@/Components/FilterSection.vue';
import Offcanvas from '@/Components/Offcanvas.vue';

defineOptions({ layout: AppLayout });

const { showToast } = useToast();

const colors = ['primary', 'success', 'warning', 'danger', 'info', 'neutral'];

const weekLabels = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
const weekOrders = [42, 58, 39, 71, 64, 88, 53];
const weekRevenue = [3200, 4100, 3800, 5200, 4800, 6100, 4500];
const categoryLabels = ['Kumaş', 'Aksesuar', 'İplik', 'Ambalaj'];
const categoryData = [42, 26, 18, 14];
const sparkData = [4, 7, 5, 9, 6, 11, 8, 13];

const notifications = ref([
    { id: 1, title: 'Yeni sipariş alındı', message: '#SP-2451 numaralı sipariş oluşturuldu.', time: '2 dk önce', read: false },
    { id: 2, title: 'Stok uyarısı', message: 'Pamuklu kumaş stoku kritik seviyede.', time: '1 saat önce', read: false },
    { id: 3, title: 'Ödeme onaylandı', message: '#SP-2438 için ödeme alındı.', time: 'Dün', read: true },
]);

function onDismissNotification(id) {
    showToast({ type: 'info', title: 'Bildirim kapatıldı', message: `#${id}` });
}

const filterOpen = ref(false);
const filterSearch = ref('');

function onFilterApply() {
    showToast({ type: 'success', title: 'Filtre uygulandı' });
}

function onFilterClear() {
    filterSearch.value = '';
    showToast({ type: 'info', title: 'Filtre temizlendi' });
}

const offcanvasOpen = ref(false);

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
