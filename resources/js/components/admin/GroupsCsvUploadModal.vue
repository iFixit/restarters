<template>
    <div v-if="show" class="modal fade show d-block" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa fa-upload mr-2"></i>
                        Upload CSV File for Bulk Group Creation
                    </h5>
                    <button type="button" class="btn-close" @click="$emit('close')" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <form @submit.prevent="$emit('upload', selectedFile)">
                    <div class="modal-body">
                        <!-- CSV Format Requirements -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <h6>
                                        <i class="fa fa-info-circle"></i>
                                        CSV Format Requirements:
                                    </h6>
                                    <p class="mb-2">Your CSV file should contain the following columns in order:</p>
                                    <ol class="mb-2">
                                        <li><strong>Name</strong> (required) - Group name</li>
                                        <li><strong>Location</strong> (required) - Group location</li>
                                        <li><strong>Postcode</strong> - Postal code</li>
                                        <li><strong>Area</strong> - Area or region</li>
                                        <li><strong>Country Code</strong> - ISO 2-letter country code (e.g., GB, US, DE)
                                        </li>
                                        <li><strong>Latitude</strong> - Geographic latitude</li>
                                        <li><strong>Longitude</strong> - Geographic longitude</li>
                                        <li><strong>Website</strong> - Group website URL</li>
                                        <li><strong>Phone</strong> - Contact phone number</li>
                                        <li><strong>Email</strong> - Contact email address</li>
                                        <li><strong>Networks</strong> - Comma-separated list of network names</li>
                                        <li><strong>Description</strong> - Group description</li>
                                    </ol>
                                    <p class="mb-0">
                                        <strong>Note:</strong> New groups will be created as unapproved and require
                                        manual approval.
                                        <button type="button" class="btn btn-sm btn-outline-primary ml-2"
                                            @click="downloadTemplate">
                                            <i class="fa fa-download mr-1"></i>
                                            Download Template
                                        </button>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- File Upload -->
                        <div class="form-group">
                            <label for="csv_file" class="form-label">
                                <strong>Select CSV File:</strong>
                            </label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="csv_file" ref="fileInput"
                                    accept=".csv,.txt" @change="handleFileSelect" required>
                                <label class="custom-file-label" for="csv_file">
                                    {{ fileName || 'Choose file...' }}
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                Maximum file size: 10MB. Supported formats: .csv, .txt
                            </small>
                        </div>

                        <!-- File Preview -->
                        <div v-if="filePreview" class="mt-3">
                            <h6>File Preview:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th v-for="(header, index) in filePreview.headers" :key="index">
                                                {{ header }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, rowIndex) in filePreview.rows" :key="rowIndex">
                                            <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="csv-cell">
                                                <span v-if="cell && cell.trim()" class="cell-content">{{ cell }}</span>
                                                <span v-else class="text-muted font-italic">(empty)</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <small class="text-muted">
                                Showing first {{ filePreview.rows.length }} rows of {{ filePreview.totalRows }} total
                                rows
                            </small>
                        </div>

                        <!-- Upload Progress -->
                        <div v-if="uploadProgress > 0" class="mt-3">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" :style="{ width: uploadProgress + '%' }"
                                    :aria-valuenow="uploadProgress" aria-valuemin="0" aria-valuemax="100">
                                    {{ uploadProgress }}%
                                </div>
                            </div>
                        </div>

                        <!-- Error Messages -->
                        <div v-if="errors.length > 0" class="alert alert-danger mt-3">
                            <h6>Upload Errors:</h6>
                            <ul class="mb-0">
                                <li v-for="(error, index) in errors" :key="index">
                                    {{ error }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')" :disabled="uploading">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="!selectedFile || uploading">
                            <i v-if="uploading" class="fa fa-spinner fa-spin mr-1"></i>
                            <i v-else class="fa fa-upload mr-1"></i>
                            {{ uploading ? 'Uploading...' : 'Upload and Create Groups' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>


    </div>
</template>

<script>

export default {
    name: "GroupsCsvUploadModal",

    props: {
        show: {
            type: Boolean,
            default: false,
        },

        uploadProgress: {
            type: Number,
            default: 0,
        },

        uploading: {
            type: Boolean,
            default: false,
        },

        upload(file) {
            this.$emit('upload', file);
        },
    },

    data() {
        return {
            selectedFile: null,
            fileName: "",
            filePreview: null,
            errors: [],
        };
    },

    watch: {
        show(newValue) {
            if (newValue) {
                // Reset form when modal opens
                this.resetForm();
                // Prevent body scroll
                document.body.style.overflow = "hidden";
            } else {
                // Restore body scroll
                document.body.style.overflow = "";
            }
        },
    },

    beforeUnmount() {
        // Ensure body scroll is restored
        document.body.style.overflow = "";
    },

    methods: {
        handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validate file size (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                this.errors = ["File size must be less than 10MB"];
                return;
            }

            // Validate file type
            if (!file.name.match(/\.(csv|txt)$/i)) {
                this.errors = ["Please select a valid CSV or TXT file"];
                return;
            }

            this.selectedFile = file;
            this.fileName = file.name;
            this.errors = [];

            // Preview file content
            this.previewFile(file);
        },

        previewFile(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const csvText = e.target.result;
                    const lines = csvText.split("\n").filter((line) => line.trim());

                    if (lines.length === 0) {
                        this.errors = ["CSV file appears to be empty"];
                        return;
                    }

                    // Parse CSV properly handling quoted fields and commas
                    const headers = this.parseCSVLine(lines[0]);
                    const rows = lines
                        .slice(1, 4)
                        .map((line) => this.parseCSVLine(line))
                        .map((row) => this.normalizeRowToHeaders(row, headers.length));

                    this.filePreview = {
                        headers,
                        rows,
                        totalRows: lines.length - 1, // Exclude header
                    };

                    // Validate required columns
                    const requiredColumns = ["Name", "Location"];
                    const missingColumns = requiredColumns.filter(
                        (col) =>
                            !headers.some(
                                (header) => header.toLowerCase() === col.toLowerCase(),
                            ),
                    );

                    if (missingColumns.length > 0) {
                        this.errors = [
                            `Missing required columns: ${missingColumns.join(", ")}`,
                        ];
                    }
                } catch (error) {
                    this.errors = [
                        "Error reading CSV file. Please check the file format.",
                    ];
                }
            };

            reader.readAsText(file);
        },

        parseCSVLine(line) {
            const result = [];
            let current = "";
            let inQuotes = false;
            let i = 0;

            // Trim the line to remove any trailing whitespace/newlines
            const trimmedLine = line.trim();

            while (i < trimmedLine.length) {
                const char = trimmedLine[i];

                if (char === '"') {
                    if (inQuotes) {
                        // Check if this is an escaped quote (double quote)
                        if (i + 1 < trimmedLine.length && trimmedLine[i + 1] === '"') {
                            // Escaped quote - add one quote to current and skip both
                            current += '"';
                            i += 2;
                        } else {
                            // End of quoted field
                            inQuotes = false;
                            i++;
                        }
                    } else {
                        // Start of quoted field
                        inQuotes = true;
                        i++;
                    }
                } else if (char === "," && !inQuotes) {
                    // Field separator found outside quotes
                    result.push(this.cleanCsvField(current));
                    current = "";
                    i++;
                } else {
                    // Regular character - add to current field
                    current += char;
                    i++;
                }
            }

            // Add the last field
            result.push(this.cleanCsvField(current));
            return result;
        },

        cleanCsvField(field) {
            // Remove leading/trailing whitespace
            let cleanedField = field.trim();

            // Remove surrounding quotes if present
            if (cleanedField.startsWith('"') && cleanedField.endsWith('"')) {
                cleanedField = cleanedField.slice(1, -1);
                // Handle escaped quotes within the field
                cleanedField = cleanedField.replace(/""/g, '"');
            }

            return cleanedField;
        },

        normalizeRowToHeaders(row, headerCount) {
            // Ensure each row has the same number of columns as headers
            if (row.length === headerCount) {
                return row;
            }
            if (row.length < headerCount) {
                // Pad with empty strings
                return [...row, ...Array(headerCount - row.length).fill("")];
            }
            // Truncate if too many columns
            return row.slice(0, headerCount);
        },

        async downloadTemplate() {
            // Create CSV template
            const headers = [
                "Name",
                "Location",
                "Postcode",
                "Area",
                "Country Code",
                "Latitude",
                "Longitude",
                "Website",
                "Phone",
                "Email",
                "Networks",
                "Description",
            ];

            const sampleData = [
                "Example Repair Cafe",
                "Community Centre, Main Street",
                "SW1A 1AA",
                "Westminster",
                "GB",
                "51.5074",
                "-0.1278",
                "https://example.com",
                "+44 20 7123 4567",
                "info@example.com",
                "Repair Cafe,Community Groups",
                "A friendly repair cafe in the heart of Westminster",
            ];

            const csvContent = [
                headers.join(","),
                sampleData.map((field) => `"${field}"`).join(","),
            ].join("\n");

            const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "groups_template.csv";
            link.click();
            URL.revokeObjectURL(link.href);
        },

        resetForm() {
            this.selectedFile = null;
            this.fileName = "";
            this.filePreview = null;
            this.errors = [];

            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = "";
            }
        },
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
    max-height: 70vh;
    overflow-y: auto;
}

.form-label {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.custom-file {
    position: relative;
    display: inline-block;
    width: 100%;
    height: calc(1.5em + 0.75rem + 2px);
    margin-bottom: 0;
}

.custom-file-input {
    position: relative;
    z-index: 2;
    width: 100%;
    height: calc(1.5em + 0.75rem + 2px);
    margin: 0;
    opacity: 0;
}

.custom-file-label {
    position: absolute;
    top: 0;
    right: 0;
    left: 0;
    z-index: 1;
    height: calc(1.5em + 0.75rem + 2px);
    padding: 0.375rem 0.75rem;
    font-weight: 400;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    cursor: pointer;
}

.custom-file-label::after {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 3;
    display: block;
    height: calc(1.5em + 0.75rem);
    padding: 0.375rem 0.75rem;
    line-height: 1.5;
    color: #495057;
    content: "Browse";
    background-color: #e9ecef;
    border-left: inherit;
    border-radius: 0 0.25rem 0.25rem 0;
}

.table-responsive {
    max-height: 200px;
    overflow-y: auto;
}

.csv-cell {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
    vertical-align: top;
}

.cell-content {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.progress {
    height: 1.5rem;
    background-color: #e9ecef;
    border-radius: 0.375rem;
}

.progress-bar {
    background-color: #007bff;
    transition: width 0.3s ease;
}

.alert {
    border-radius: 0.375rem;
}

.alert h6 {
    margin-bottom: 0.5rem;
}

.alert ol,
.alert ul {
    padding-left: 1.5rem;
}

.btn {
    font-weight: 500;
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    /* Prevent layout shift by maintaining consistent border width */
    border-width: 1px;
}

.btn:hover {
    /* Ensure border width doesn't change on hover */
    border-width: 1px;
}

.btn-sm {
    /* Ensure small buttons also maintain consistent border width */
    border-width: 1px;
}

.btn-sm:hover {
    /* Prevent layout shift on small buttons */
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

.modal-backdrop {
    z-index: 1040;
}
</style>
