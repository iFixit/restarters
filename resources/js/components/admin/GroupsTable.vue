<template>
    <div class="groups-table">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th class="checkbox-column">
                            <input type="checkbox" :checked="allSelected" @change="handleSelectAll"
                                :disabled="loading" />
                        </th>
                        <th @click="handleSort('name')" class="sortable">
                            Name
                            <i :class="getSortIcon('name')"></i>
                        </th>
                        <th @click="handleSort('location')" class="sortable">
                            Location
                            <i :class="getSortIcon('location')"></i>
                        </th>
                        <th @click="handleSort('confirmed_hosts_count')" class="sortable text-center">
                            Hosts
                            <i :class="getSortIcon('confirmed_hosts_count')"></i>
                        </th>
                        <th @click="handleSort('confirmed_restarters_count')" class="sortable text-center">
                            Volunteers
                            <i :class="getSortIcon('confirmed_restarters_count')"></i>
                        </th>
                        <th @click="handleSort('approved')" class="sortable text-center">
                            Status
                            <i :class="getSortIcon('approved')"></i>
                        </th>
                        <th @click="handleSort('created_at')" class="sortable">
                            Created At
                            <i :class="getSortIcon('created_at')"></i>
                        </th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="8" class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-else-if="groups.length === 0">
                        <td colspan="8" class="text-center text-muted">
                            No groups found
                        </td>
                    </tr>
                    <tr v-else v-for="group in groups" :key="group.idgroups"
                        :class="{ 'table-active': isSelected(group) }">
                        <td>
                            <input type="checkbox" :checked="isSelected(group)" @change="handleSelect(group, $event)"
                                :disabled="loading" />
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div>
                                    <a v-if="!group.deleted_at" :href="'/group/view/' + group.idgroups" class="fw-bold">{{ group.name }}</a>
                                    <span v-else class="fw-bold">{{ group.name }}</span>
                                    <span v-if="group.archived_at"
                                        class="badge nounderline badge-secondary badge-pill">Archived</span>
                                    <span v-if="group.deleted_at"
                                        class="badge nounderline badge-danger badge-pill">Deleted</span>
                                    <div v-if="group.networks && group.networks.length > 0" class="small text-muted">
                                        {{group.networks.map(n => n.name).join(', ')}}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div v-if="group.location">
                                {{ group.location }}
                            </div>
                            <div v-else class="text-muted">No location</div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary">{{ group.confirmed_hosts_count || 0 }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success">{{ group.confirmed_restarters_count || 0 }}</span>
                        </td>
                        <td class="text-center">
                            <span v-if="group.approved" class="badge bg-success">Approved</span>
                            <span v-else class="badge bg-warning">Pending</span>
                        </td>
                        <td>
                            <div class="small">{{ formatDate(group.created_at) }}</div>
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                                    :id="'dropdown-' + group.idgroups" data-toggle="dropdown" data-boundary="viewport"
                                    data-flip="false" aria-expanded="false">
                                    Actions
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right"
                                    :aria-labelledby="'dropdown-' + group.idgroups">
                                    <li v-if="!group.deleted_at">
                                        <a class="dropdown-item" :href="'/group/edit/' + group.idgroups">
                                            Edit
                                        </a>
                                    </li>
                                    <li v-if="!group.archived_at">
                                        <a class="dropdown-item" @click="handleAction(group, 'archive')">
                                            Archive
                                        </a>
                                    </li>
                                    <li v-if="group.archived_at">
                                        <a class="dropdown-item" @click="handleAction(group, 'unarchive')">
                                            Unarchive
                                        </a>
                                    </li>
                                    <li v-if="!group.approved">
                                        <a class="dropdown-item" @click="handleAction(group, 'approve')">
                                            Approve
                                        </a>
                                    </li>
                                    <li v-if="group.approved">
                                        <a class="dropdown-item" @click="handleAction(group, 'unapprove')">
                                            Unapprove
                                        </a>
                                    </li>
                                    <li v-if="!group.deleted_at">
                                        <a class="dropdown-item text-danger" @click="handleAction(group, 'delete')">
                                            Delete
                                        </a>
                                    </li>
                                    <li v-if="group.deleted_at">
                                        <a class="dropdown-item text-success" @click="handleAction(group, 'restore')">
                                            Restore
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Page Size Selector Header -->
        <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
            <div class="d-flex align-items-center">
                <label class="mr-2 mb-0">Show:</label>
                <select class="form-control form-control-sm" style="width: 80px;" :value="pagination.perPage"
                    @change="handlePageSizeChange">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                </select>
                <span class="ml-2 text-muted">per page</span>
            </div>
            <div class="text-muted" v-if="pagination.total > 0">
                Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} groups
            </div>
        </div>

        <!-- Pagination Footer -->
        <div v-if="pagination.total > 0 && pagination.totalPages > 1" class="d-flex justify-content-center mt-3">
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item" :class="{ disabled: pagination.currentPage === 1 }">
                        <a class="page-link" @click="changePage(pagination.currentPage - 1)" href="#">
                            Previous
                        </a>
                    </li>
                    <li v-for="page in visiblePages" :key="page" class="page-item"
                        :class="{ active: page === pagination.currentPage }">
                        <a class="page-link" @click="changePage(page)" href="#">
                            {{ page }}
                        </a>
                    </li>
                    <li class="page-item" :class="{ disabled: pagination.currentPage === pagination.totalPages }">
                        <a class="page-link" @click="changePage(pagination.currentPage + 1)" href="#">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</template>

<script>
import moment from "moment";

export default {
    name: "GroupsTable",
    props: {
        groups: {
            type: Array,
            default: () => [],
        },
        loading: {
            type: Boolean,
            default: false,
        },
        selectedGroups: {
            type: Array,
            default: () => [],
        },
        pagination: {
            type: Object,
            default: () => ({
                currentPage: 1,
                totalPages: 0,
                total: 0,
                perPage: 25,
                from: 0,
                to: 0,
            }),
        },
        sortField: {
            type: String,
            default: "name",
        },
        sortDirection: {
            type: String,
            default: "asc",
        },
        deletedFilter: {
            type: String,
            default: "active",
        },
    },

    computed: {
        allSelected() {
            return (
                this.groups.length > 0 &&
                this.selectedGroups.length === this.groups.length
            );
        },

        visiblePages() {
            const pages = [];
            const current = this.pagination.currentPage;
            const last = this.pagination.totalPages;

            // Show max 7 pages
            let start = Math.max(1, current - 3);
            let end = Math.min(last, current + 3);

            // Adjust if we're near the beginning or end
            if (current <= 4) {
                end = Math.min(last, 7);
            }
            if (current >= last - 3) {
                start = Math.max(1, last - 6);
            }

            for (let i = start; i <= end; i++) {
                pages.push(i);
            }

            return pages;
        },
    },

    methods: {
        isSelected(group) {
            return this.selectedGroups.some((g) => g.idgroups === group.idgroups);
        },

        handleSelect(group, event) {
            this.$emit("select", group, event.target.checked);
        },

        handleSelectAll(event) {
            this.$emit("select-all", event.target.checked);
        },

        handleSort(field) {
            const direction =
                this.sortField === field && this.sortDirection === "asc"
                    ? "desc"
                    : "asc";
            this.$emit("sort-change", field, direction);
        },

        handleAction(group, action) {
            this.$emit("action", group, action);
        },

        getSortIcon(field) {
            if (this.sortField !== field) {
                return "sort-icon";
            }
            return this.sortDirection === "asc"
                ? "sort-icon sort-up"
                : "sort-icon sort-down";
        },

        changePage(page) {
            if (page >= 1 && page <= this.pagination.totalPages) {
                this.$emit("page-change", page);
            }
        },

        handlePageSizeChange(event) {
            const newPageSize = Number.parseInt(event.target.value);
            this.$emit("page-size-change", newPageSize);
        },

        formatDate(dateString) {
            if (!dateString) return "";
            return moment(dateString).format("MMM DD, YYYY");
        },
    },
};
</script>

<style scoped>
.groups-table {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.table {
    table-layout: fixed;
    width: 100%;
}

.table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
}

.sortable {
    cursor: pointer;
    user-select: none;
}

.sortable:hover {
    background-color: #e9ecef;
}

.checkbox-column {
    width: 40px;
}

.actions-column {
    width: 120px;
}

/* Column width specifications to prevent layout shift */
.table th:nth-child(1),
.table td:nth-child(1) {
    width: 40px;
}

/* Checkbox */

.table th:nth-child(2),
.table td:nth-child(2) {
    width: 25%;
}

/* Name */

.table th:nth-child(3),
.table td:nth-child(3) {
    width: 20%;
}

/* Location */

.table th:nth-child(4),
.table td:nth-child(4) {
    width: 10%;
}

/* Hosts */

.table th:nth-child(5),
.table td:nth-child(5) {
    width: 10%;
}

/* Volunteers */

.table th:nth-child(6),
.table td:nth-child(6) {
    width: 10%;
}

/* Status */

.table th:nth-child(7),
.table td:nth-child(7) {
    width: 15%;
}

/* Created At */

.table th:nth-child(8),
.table td:nth-child(8) {
    width: 120px;
}

/* Actions */

/* Prevent text overflow in fixed-width cells */
.table td {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Allow wrapping for specific content */
.table td:nth-child(2),
/* Name column */
.table td:nth-child(3) {
    /* Location column */
    white-space: normal;
    word-wrap: break-word;
}

.table-active {
    background-color: rgba(0, 123, 255, 0.1);
}

.dropdown-toggle::after {
    margin-left: 0.5em;
}

.pagination {
    margin-top: 20px;
}

.page-link {
    cursor: pointer;
}

.page-item.disabled .page-link {
    cursor: not-allowed;
}

/* Custom sort icons */
.sort-icon {
    display: inline-block;
    width: 0;
    height: 0;
    margin-left: 8px;
    vertical-align: middle;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-bottom: 4px solid #6c757d;
    opacity: 0.3;
    transition: opacity 0.15s ease;
}

.sort-icon.sort-up {
    border-bottom: 4px solid #495057;
    border-top: none;
    opacity: 1;
}

.sort-icon.sort-down {
    border-top: 4px solid #495057;
    border-bottom: none;
    opacity: 1;
}

/* Prevent layout shift by reserving space for sort icons */
.sortable {
    position: relative;
    padding-right: 20px;
    /* Reserve space for the icon */
}

.sortable .sort-icon {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    margin-left: 0;
    /* Remove left margin since we're using absolute positioning */
}

.dropdown-menu {
    position: absolute;
    z-index: 1050;
}

.table-responsive {
    /* Remove overflow: hidden to allow dropdowns to escape table bounds */
    overflow-x: auto;
    /* Do not set overflow: hidden here! */
}

/* Form control styling */
.form-control-sm {
    height: calc(1.5em + 0.5rem + 2px);
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.2rem;
}

/* Pagination styling to match GroupsManagement */
.pagination {
    margin-bottom: 0;
}

.page-link {
    cursor: pointer;
    border-color: #dee2e6 !important;
}

.page-link:hover {
    border-color: #adb5bd !important;
    background-color: #e9ecef !important;
    color: #222 !important;
}

.page-item.active .page-link {
    border-color: #222 !important;
    background-color: #222 !important;
    color: white !important;
}

.page-item.disabled .page-link {
    cursor: not-allowed;
    border-color: #dee2e6 !important;
}
</style>