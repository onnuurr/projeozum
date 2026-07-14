<template>
  <div class="data-table-container">
    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th 
            v-for="column in columns" 
            :key="column.key"
            :style="column.width ? { width: column.width } : {}"
          >
            {{ column.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, index) in data" :key="row.id || index">
          <td v-for="column in columns" :key="column.key">
            <template v-if="column.type === 'user'">
              <div class="user-cell">
                <div 
                  class="user-avatar" 
                  :style="{ background: row.avatarGradient || 'linear-gradient(135deg, rgb(var(--color-primary)), rgb(var(--color-primary-hover)))' }"
                >
                  {{ row.initials || getInitials(row[column.field]) }}
                </div>
                <div class="user-info">
                  <span class="user-name">{{ row[column.field] }}</span>
                  <span class="user-email" v-if="row.email">{{ row.email }}</span>
                </div>
              </div>
            </template>
            
            <template v-else-if="column.type === 'badge'">
              <span class="role-badge" :class="column.classPrefix + row[column.field]">
                {{ row[column.field] }}
              </span>
            </template>
            
            <template v-else-if="column.type === 'status'">
              <span class="status-badge" :class="column.statusPrefix + row[column.field]">
                {{ row[column.field] }}
              </span>
            </template>
            
            <template v-else-if="column.type === 'actions'">
              <div class="table-actions">
                <button 
                  v-for="action in actions" 
                  :key="action.name"
                  class="table-action-btn"
                  :class="action.class"
                  @click="$emit('action', { name: action.name, row })"
                  :title="action.label"
                >
                  {{ action.icon }} {{ action.label }}
                </button>
              </div>
            </template>
            
            <template v-else-if="column.type === 'custom'">
              <slot :name="column.key" :row="row" :value="row[column.field]"></slot>
            </template>
            
            <template v-else>
              {{ row[column.field] }}
            </template>
          </td>
        </tr>
      </tbody>
    </table>
    </div>

    <div class="pagination" v-if="pagination">
      <div class="pagination-info">
        {{ paginationText }}
      </div>
      <div class="pagination-controls">
        <button 
          class="pagination-btn" 
          :disabled="currentPage === 1"
          @click="$emit('page-change', currentPage - 1)"
        >
          ‹
        </button>
        <button 
          v-for="page in visiblePages" 
          :key="page"
          class="pagination-btn"
          :class="{ active: currentPage === page }"
          @click="$emit('page-change', page)"
        >
          {{ page }}
        </button>
        <button 
          class="pagination-btn" 
          :disabled="currentPage === totalPages"
          @click="$emit('page-change', currentPage + 1)"
        >
          ›
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  columns: {
    type: Array,
    required: true
  },
  data: {
    type: Array,
    required: true
  },
  actions: {
    type: Array,
    default: () => []
  },
  pagination: {
    type: Object,
    default: null
  },
  currentPage: {
    type: Number,
    default: 1
  }
})

const emit = defineEmits(['action', 'page-change'])

const getInitials = (name) => {
  if (!name) return '?'
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
}

const totalPages = computed(() => {
  if (!props.pagination) return 1
  return Math.ceil(props.pagination.total / props.pagination.perPage)
})

const paginationText = computed(() => {
  if (!props.pagination) return ''
  const start = (props.currentPage - 1) * props.pagination.perPage + 1
  const end = Math.min(props.currentPage * props.pagination.perPage, props.pagination.total)
  return `Toplam ${props.pagination.total} kayıt (${start}-${end} arası)`
})

const visiblePages = computed(() => {
  const pages = []
  const total = totalPages.value
  const current = props.currentPage
  
  if (total <= 5) {
    for (let i = 1; i <= total; i++) pages.push(i)
  } else {
    if (current <= 3) {
      pages.push(1, 2, 3, 4, '...', total)
    } else if (current >= total - 2) {
      pages.push(1, '...', total - 3, total - 2, total - 1, total)
    } else {
      pages.push(1, '...', current - 1, current, current + 1, '...', total)
    }
  }
  
  return pages.filter(p => p !== '...')
})
</script>

<style scoped>
.data-table-container {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #ebebf0;
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.data-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

.data-table thead tr {
  background: #f8f8fc;
}

.data-table th {
  text-align: left;
  padding: 14px 16px;
  font-size: 11px;
  font-weight: 600;
  color: #aaa;
  border-bottom: 1px solid #f0f0f5;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.data-table td {
  padding: 14px 16px;
  font-size: 13px;
  color: #444;
  border-bottom: 1px solid #f5f5f8;
  vertical-align: middle;
}

.data-table tr:last-child td {
  border-bottom: none;
}

.data-table tr:hover td {
  background: #fafafe;
}

.user-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-weight: 600;
  color: #1a1a2e;
  font-size: 13px;
}

.user-email {
  font-size: 11px;
  color: #888;
}

.role-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
}

.role-badge.role-admin { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.role-badge.role-manager { background: #e0f2fe; color: #0284c7; }
.role-badge.role-user { background: #f0fdf4; color: #16a34a; }
.role-badge.role-operator { background: #fff7ed; color: #ea580c; }

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
}

.status-badge.status-active { background: #dcfce7; color: #16a34a; }
.status-badge.status-inactive { background: #fee2e2; color: #dc2626; }
.status-badge.status-pending { background: #fef9c3; color: #ca8a04; }

.table-actions {
  display: flex;
  gap: 4px;
}

.table-action-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 12px;
  padding: 5px 8px;
  border-radius: 6px;
  font-weight: 500;
  transition: all .15s;
}

.table-action-btn.view {
  color: #6b7280;
  background: #f3f4f6;
}

.table-action-btn.view:hover {
  background: rgb(var(--color-primary-soft));
  color: rgb(var(--color-primary));
}

.table-action-btn.edit {
  color: #6b7280;
  background: #f3f4f6;
}

.table-action-btn.edit:hover {
  background: #dcfce7;
  color: #16a34a;
}

.table-action-btn.delete {
  color: #6b7280;
  background: #f3f4f6;
}

.table-action-btn.delete:hover {
  background: #fee2e2;
  color: #dc2626;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-top: 1px solid #f0f0f5;
}

.pagination-info {
  font-size: 13px;
  color: #888;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 4px;
}

.pagination-btn {
  min-width: 32px;
  height: 32px;
  padding: 0 8px;
  border: 1px solid #e8e8f0;
  border-radius: 6px;
  background: #fff;
  color: #666;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all .15s;
}

.pagination-btn:hover {
  border-color: #ccc;
  color: #333;
  background: #f9f9fb;
}

.pagination-btn.active {
  background: #1a1a2e;
  color: #fff;
  border-color: #1a1a2e;
}

.pagination-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
</style>