<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Download, FileSpreadsheet, FileText, RefreshCw, Trash2 } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import StatusBadge from '@ahmad-chebbo/tessera-ui/Components/StatusBadge.vue';
import { useConfirm } from '@ahmad-chebbo/tessera-ui/Composables/useConfirm';

const props = defineProps({
    exports: Array,
    keepDays: Number,
});

const { t } = useI18n();
const confirm = useConfirm();

const columns = computed(() => [
    { key: 'report', label: t('exports.report') },
    { key: 'format', label: t('exports.format') },
    { key: 'status', label: t('exports.status') },
    { key: 'rows', label: t('exports.rows') },
    { key: 'size', label: t('exports.size') },
    { key: 'created_at', label: t('exports.created'), cellClass: 'text-sm text-muted' },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const tone = { queued: 'neutral', processing: 'info', ready: 'success', failed: 'danger' };
const active = computed(() => props.exports.some((file) => ['queued', 'processing'].includes(file.status)));

const formatDate = (value) => (value ? new Date(value).toLocaleString() : '-');
const formatSize = (bytes) => {
    if (!bytes) {
        return '-';
    }

    return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const refresh = () => router.reload({ only: ['exports'], preserveScroll: true });

let timer = null;
onMounted(() => {
    timer = setInterval(() => {
        if (active.value) {
            refresh();
        }
    }, 4000);
});
onBeforeUnmount(() => clearInterval(timer));

const download = (file) => window.location.assign(file.download_url);

const remove = async (file) => {
    if (await confirm.delete(t('exports.confirmDelete'))) {
        router.delete(route('exports.destroy', file.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('exports.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('exports.title') }}</h1>
                <p class="text-sm text-muted">{{ t('exports.subtitle') }} · {{ t('exports.keepHint', { days: keepDays }) }}</p>
            </div>
        </template>

        <div class="mb-6 flex justify-end">
            <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line" @click="refresh">
                <RefreshCw class="h-4 w-4" />
                {{ t('exports.refresh') }}
            </button>
        </div>

        <DataTable :columns="columns" :rows="exports" :empty-title="t('exports.noExports')">
            <template #cell-report="{ row }">{{ t(`exports.reports.${row.report}`) }}</template>
            <template #cell-format="{ row }">
                <span class="inline-flex items-center gap-2">
                    <component :is="row.format === 'pdf' ? FileText : FileSpreadsheet" class="h-4 w-4 text-muted" />
                    {{ row.format === 'pdf' ? 'PDF' : 'Excel' }}
                </span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :status="tone[row.status]">{{ t(`exports.statuses.${row.status}`) }}</StatusBadge>
            </template>
            <template #cell-rows="{ row }">{{ row.rows ?? row.estimated_rows }}</template>
            <template #cell-size="{ row }">{{ formatSize(row.size) }}</template>
            <template #cell-created_at="{ row }">{{ formatDate(row.created_at) }}</template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton v-if="row.download_url" :icon="Download" :label="t('exports.download')" @click="download(row)" />
                    <IconButton :icon="Trash2" :label="t('exports.delete')" variant="danger" @click="remove(row)" />
                </div>
            </template>
        </DataTable>
    </SidebarLayout>
</template>
