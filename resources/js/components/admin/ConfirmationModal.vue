<template>
    <div v-if="show" class="modal fade show d-block" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ title }}
                    </h5>
                    <button type="button" class="btn-close" @click="$emit('cancel')" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div v-if="groups.length <= 5" class="mt-3">
                        <h6>Affected Groups</h6>
                        <ul class="list-group">
                            <li v-for="group in groups" :key="group.id"
                                class="list-group-item d-flex align-items-center">
                                <div>
                                    <strong>{{ group.name }}</strong>
                                    <div class="small text-muted">{{ group.location }}</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div v-if="error" class="alert alert-danger">
                    {{ error }}
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="$emit('cancel')">
                        Cancel
                    </button>
                    <button type="button" :class="confirmButtonClass" class="btn" @click="$emit('confirm')">
                        {{ confirmButtonText }}
                    </button>
                </div>
            </div>
        </div>


    </div>
</template>

<script>
export default {
    name: "ConfirmationModal",

    props: {
        show: {
            type: Boolean,
            default: false,
        },
        action: {
            type: String,
            default: null,
        },
        groups: {
            type: Array,
            default: () => [],
        },
        error: {
            type: String,
            default: null,
        },
    },

    computed: {
        title() {
            const actions = {
                delete: "Deletion",
                approve: "Approval",
                unapprove: "Unapproval",
                archive: "Archiving",
                unarchive: "Unarchiving",
            };
            return `Confirm ${actions[this.action]}` || "Confirm Action";
        },

        confirmButtonClass() {
            const classes = {
                delete: "btn btn-danger",
                approve: "btn btn-success",
                unapprove: "btn btn-warning",
                archive: "btn btn-info",
                unarchive: "btn-outline-info",
            };
            return classes[this.action] || "btn btn-primary";
        },

        confirmButtonText() {
            const texts = {
                delete: "Delete",
                approve: "Approve",
                unapprove: "Unapprove",
                archive: "Archive",
                unarchive: "Unarchive",
            };
            return texts[this.action]
        },
    },

    watch: {
        show(newValue) {
            if (newValue) {
                // Prevent body scroll when modal is open
                document.body.style.overflow = "hidden";
            } else {
                // Restore body scroll when modal is closed
                document.body.style.overflow = "";
            }
        },
    },

    beforeUnmount() {
        // Ensure body scroll is restored when component is destroyed
        document.body.style.overflow = "";
    },
};
</script>

<style scoped>
.modal {
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1050;
}

.modal-dialog {
    margin: 1.75rem auto;
    max-width: 500px;
}

.modal-content {
    border: none;
    border-radius: 0.5rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.modal-header {
    border-bottom: 1px solid #dee2e6;
    background-color: #f8f9fa;
    border-radius: 0.5rem 0.5rem 0 0;
}

.modal-title {
    font-weight: 600;
    color: #495057;
}

.modal-body {
    padding: 1.5rem;
}

.modal-body p {
    margin-bottom: 1rem;
    color: #6c757d;
    line-height: 1.5;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
    background-color: #f8f9fa;
    border-radius: 0 0 0.5rem 0.5rem;
}

.btn {
    font-weight: 500;
    padding: 0.5rem 1.5rem;
    border-radius: 0.25rem;
    transition: all 0.2s ease-in-out;
    /* Prevent layout shift by maintaining consistent border width */
    border-width: 1px;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    /* Ensure border width doesn't change on hover */
    border-width: 1px;
}

.btn:disabled {
    transform: none;
    box-shadow: none;
    border-width: 1px;
}

.btn-close {
    font-size: 1.5rem;
    line-height: 1;
    color: #000;
    text-shadow: 0 1px 0 #fff;
    opacity: 0.8;
    background: none;
    border: none;
    padding: 0.25rem 0.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    margin-left: auto;
}

.btn-close:hover {
    opacity: 1;
    background-color: rgba(0, 0, 0, 0.1);
    border-radius: 0.25rem;
}

.alert {
    margin-bottom: 0;
    border-radius: 0.25rem;
}

.list-group-item {
    text-decoration: none;

    &:hover {
        text-decoration: none;
    }
}

/* Animation */
.modal.fade.show {
    animation: modalFadeIn 0.15s ease-out;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}

.modal-backdrop {
    z-index: 1040;
}
</style>