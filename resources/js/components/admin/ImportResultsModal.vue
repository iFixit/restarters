<template>
    <div v-if="show" class="modal fade show d-block" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" :class="headerClass">
                    <h5 class="modal-title">
                        <i :class="iconClass" class="mr-2"></i>
                        {{ title }}
                    </h5>
                    <button type="button" class="btn-close" @click="$emit('close')" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <!-- Success/Partial Success Summary -->
                    <div v-if="created > 0" class="alert alert-success">
                        <h6><i class="fa fa-check-circle mr-2"></i>Successfully Created</h6>
                        <p class="mb-0">{{ created }} group{{ created !== 1 ? 's' : '' }} {{ created === 1 ? 'was' :
                            'were' }} created successfully.</p>
                    </div>

                    <!-- Error Summary -->
                    <div v-if="errors.length > 0" class="alert alert-danger">
                        <h6><i class="fa fa-exclamation-circle mr-2"></i>Errors Encountered</h6>
                        <p class="mb-2">{{ errors.length }} error{{ errors.length !== 1 ? 's' : '' }} occurred during
                            the import:</p>

                        <!-- Expandable Error List -->
                        <div class="error-list">
                            <div v-for="(error, index) in displayedErrors" :key="index"
                                class="error-item mb-2 p-2 bg-light rounded">
                                <small class="text-muted">Row {{ getRowNumber(error, index) }}:</small>
                                <div class="error-message">{{ formatError(error) }}</div>
                            </div>

                            <!-- Show More/Less Button -->
                            <div v-if="errors.length > maxDisplayedErrors" class="text-center mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                    @click="toggleShowAllErrors">
                                    <i :class="showAllErrors ? 'fa fa-chevron-up' : 'fa fa-chevron-down'"
                                        class="mr-1"></i>
                                    {{ showAllErrors ? 'Show Less' : `Show ${errors.length - maxDisplayedErrors} More`
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div v-if="type === 'partial'" class="alert alert-info">
                        <h6><i class="fa fa-info-circle mr-2"></i>Next Steps</h6>
                        <ul class="mb-0">
                            <li>Review the errors above and fix the data in your CSV file</li>
                            <li>Re-upload the corrected CSV file to import the remaining groups</li>
                            <li>Successfully created groups will not be duplicated</li>
                        </ul>
                    </div>

                    <div v-else-if="type === 'error'" class="alert alert-warning">
                        <h6><i class="fa fa-exclamation-triangle mr-2"></i>Troubleshooting</h6>
                        <ul class="mb-0">
                            <li>Check that your CSV file follows the required format</li>
                            <li>Ensure all required fields (Name, Location) are provided</li>
                            <li>Verify that the file is properly encoded (UTF-8)</li>
                            <li>Download and use our CSV template if needed</li>
                        </ul>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="$emit('close')">
                        Close
                    </button>
                    <button v-if="type === 'partial' || type === 'error'" type="button" class="btn btn-primary"
                        @click="tryAgain">
                        <i class="fa fa-upload mr-1"></i>
                        Try Again
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ImportResultsModal',

    props: {
        show: {
            type: Boolean,
            default: false
        },
        type: {
            type: String,
            default: 'success' // 'success', 'partial', 'error'
        },
        created: {
            type: Number,
            default: 0
        },
        errors: {
            type: Array,
            default: () => []
        },
    },

    data() {
        return {
            showAllErrors: false,
            maxDisplayedErrors: 5
        };
    },

    computed: {
        title() {
            switch (this.type) {
                case 'success':
                    return 'Import Successful';
                case 'partial':
                    return 'Import Partially Successful';
                case 'error':
                    return 'Import Failed';
                default:
                    return 'Import Results';
            }
        },

        headerClass() {
            switch (this.type) {
                case 'success':
                    return 'bg-success text-white';
                case 'partial':
                    return 'bg-warning text-dark';
                case 'error':
                    return 'bg-danger text-white';
                default:
                    return 'bg-light';
            }
        },

        iconClass() {
            switch (this.type) {
                case 'success':
                    return 'fa fa-check-circle';
                case 'partial':
                    return 'fa fa-exclamation-triangle';
                case 'error':
                    return 'fa fa-times-circle';
                default:
                    return 'fa fa-info-circle';
            }
        },

        messageClass() {
            switch (this.type) {
                case 'success':
                    return 'alert-success';
                case 'partial':
                    return 'alert-warning';
                case 'error':
                    return 'alert-danger';
                default:
                    return 'alert-info';
            }
        },

        displayedErrors() {
            if (this.showAllErrors) {
                return this.errors;
            }
            return this.errors.slice(0, this.maxDisplayedErrors);
        }
    },

    watch: {
        show(newValue) {
            if (newValue) {
                this.showAllErrors = false;
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
    },

    beforeUnmount() {
        document.body.style.overflow = '';
    },

    methods: {
        toggleShowAllErrors() {
            this.showAllErrors = !this.showAllErrors;
        },

        formatError(error) {
            if (typeof error === 'string') {
                return error;
            }
            if (error.message) {
                return error.message;
            }
            if (error.error) {
                return error.error;
            }
            return JSON.stringify(error);
        },

        getRowNumber(error, index) {
            if (typeof error === 'object' && error.row) {
                return error.row;
            }
            return index + 2; // +2 because index is 0-based and row 1 is headers
        },

        tryAgain() {
            this.$emit('close');
            // You could emit a 'try-again' event here if you want to automatically open the upload modal
            // this.$emit('try-again');
        }
    }
};
</script>

<style scoped>
.modal {
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1055;
}

.modal-dialog {
    margin: 1.75rem auto;
}

.modal-content {
    border: none;
    border-radius: 0.5rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.modal-header {
    border-bottom: 1px solid #dee2e6;
    border-radius: 0.5rem 0.5rem 0 0;
}

.modal-title {
    font-weight: 600;
}

.modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

.alert {
    border-radius: 0.375rem;
    margin-bottom: 1rem;
}

.alert h6 {
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.error-list {
    max-height: 300px;
    overflow-y: auto;
}

.error-item {
    border-left: 3px solid #dc3545;
}

.error-message {
    font-size: 0.9rem;
    line-height: 1.4;
    white-space: pre-wrap;
    word-break: break-word;
}

.btn-close {
    font-size: 1.5rem;
    line-height: 1;
    color: inherit;
    text-shadow: none;
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
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 0.25rem;
}

.bg-warning .btn-close:hover {
    background-color: rgba(0, 0, 0, 0.1);
}

.text-muted {
    font-size: 0.8rem;
}

ul {
    padding-left: 1.5rem;
    margin-bottom: 0;
}

li {
    margin-bottom: 0.25rem;
}
</style>