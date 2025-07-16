<template>
    <div class="groups-bulk-actions" v-if="selectedGroups.length > 0">
        <div class="card bg-light">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <span class="fw-bold">
                            {{ selectedGroups.length }} of {{ totalCount }} selected
                        </span>
                    </div>
                    <div class="col-md-6">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-success" @click="handleAction('approve')"
                                :disabled="loading">
                                Approve
                            </button>
                            <button type="button" class="btn btn-warning" @click="handleAction('unapprove')"
                                :disabled="loading">
                                Unapprove
                            </button>
                            <button type="button" class="btn btn-info" @click="handleAction('archive')"
                                :disabled="loading">
                                Archive
                            </button>
                            <button type="button" class="btn btn-outline-info" @click="handleAction('unarchive')"
                                :disabled="loading">
                                Unarchive
                            </button>
                            <button type="button" class="btn btn-danger" @click="handleAction('delete')"
                                :disabled="loading">
                                Delete
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-outline-secondary" @click="handleClearSelection">
                            Clear Selection
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "GroupsBulkAction",
    props: {
        selectedGroups: {
            type: Array,
            default: () => [],
        },
        totalCount: {
            type: Number,
            default: 0,
        },
        loading: {
            type: Boolean,
            default: false,
        },
    },

    methods: {
        handleAction(action) {
            this.$emit("action", action);
        },

        handleClearSelection() {
            this.$emit("clear-selection");
        },
    },
};
</script>

<style scoped>
.groups-bulk-actions {
    margin-bottom: 20px;
}

.btn-group .btn {
    margin-right: 0.5rem;
}

.btn-group .btn:last-child {
    margin-right: 0;
}

.card {
    border: 1px solid #dee2e6;
}
</style>