<template>
  <div class="groups-management">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2>Groups Management</h2>
        <span class="text-muted"> {{ totalCount }} groups</span>
      </div>
      <div class="button-group">
        <button class="btn btn-primary mr-2" @click="handleExport" :disabled="exporting">Export Groups</button>
        <button class="btn btn-primary" @click="showCsvUploadModal = true">Import Groups</button>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="row mb-4">
      <div class="col-md-6">
        <div class="input-group">
          <input id="search" class="form-control" placeholder="Search groups by name, location, postcode, or area..."
            v-model="searchQuery" @input="handleSearchInput" />
          <div class="input-group-append" v-if="searchQuery">
            <button class="btn btn-outline-secondary" type="button" @click="clearSearch" title="Clear search">
              &times;
            </button>
          </div>
        </div>
        <small class="form-text text-muted" v-if="searchQuery">
          {{ searchResultsText }}
        </small>
      </div>
    </div>

    <div v-if="alert.message" :class="`alert alert-${alert.type}`" class="alert-dismissible fade show">
      {{ alert.message }}
      <button type="button" class="close" @click="clearAlert">
        <span>&times;</span>
      </button>
    </div>

    <GroupsCsvUploadModal :show="showCsvUploadModal" @close="showCsvUploadModal = false" @upload="handleUpload"
      :upload-progress="uploadProgress" :uploading="uploading" />

    <GroupsBulkAction :selected-groups="selectedGroups" :total-count="totalCount" @action="handleBulkAction"
      @clear-selection="clearSelection" />

    <GroupsTable :groups="groups" :loading="loading" :selected-groups="selectedGroups" :pagination="pagination"
      :sort-field="sortField" :sort-direction="sortDirection" @action="handleAction" @select="handleGroupSelect"
      @select-all="handleSelectAll" @page-change="handlePageChange" @page-size-change="handlePageSizeChange"
      @sort-change="handleSortChange" />

    <ConfirmationModal :show="confirmationModal.show" :action="confirmationModal.action"
      :groups="confirmationModal.groups" :error="confirmationModal.error" @confirm="handleModalConfirm"
      @cancel="handleModalCancel" />

    <ImportResultsModal :show="importResults.show" :type="importResults.type" :created="importResults.created"
      :errors="importResults.errors" @close="closeImportResults" />

    <!-- Floating Scroll to Top Button -->
    <div class="scroll-to-top-btn" :class="{ 'show': showScrollToTop }" @click="scrollToTop" title="Scroll to top">
      <span class="chevron-up">▲</span>
    </div>

  </div>
</template>

<script>
import GroupsTable from "./GroupsTable.vue";
import GroupsBulkAction from "./GroupsBulkAction.vue";
import ConfirmationModal from "./ConfirmationModal.vue";
import GroupsCsvUploadModal from "./GroupsCsvUploadModal.vue";
import ImportResultsModal from "./ImportResultsModal.vue";
import groupsApi from "../../api/groups.js";

export default {
  name: "GroupsManagement",
  components: {
    GroupsTable,
    GroupsBulkAction,
    ConfirmationModal,
    GroupsCsvUploadModal,
    ImportResultsModal,
  },

  data() {
    return {
      groups: [],
      loading: false,
      selectedGroups: [],
      totalCount: 0,
      showCsvUploadModal: false,
      exporting: false,
      confirmationModal: {
        show: false,
        action: null,
        groups: [],
        error: null,
      },
      alert: {
        message: "",
        type: "success",
      },

      pagination: {
        currentPage: 1,
        perPage: 25,
        totalPages: 0,
        total: 0,
        from: 0,
        to: 0,
      },
      sortField: "name",
      sortDirection: "asc",

      searchQuery: "",
      searchTimeout: null,

      uploadProgress: 0,
      uploading: false,

      importResults: {
        show: false,
        type: 'success', // 'success', 'partial', 'error'
        created: 0,
        errors: [],
      },

      showScrollToTop: false,
    };
  },

  mounted() {
    this.loadGroups();
    window.addEventListener('scroll', this.handleScroll);
  },

  computed: {
    searchResultsText() {
      if (!this.searchQuery) return '';

      if (this.loading) {
        return 'Searching...';
      }

      return `Found ${this.totalCount} result${this.totalCount !== 1 ? 's' : ''} for "${this.searchQuery}"`;
    }
  },

  beforeDestroy() {
    window.removeEventListener('scroll', this.handleScroll);
    // Clear search timeout to prevent memory leaks
    if (this.searchTimeout) {
      clearTimeout(this.searchTimeout);
    }
  },

  methods: {
    async loadGroups() {
      this.loading = true;
      try {
        const params = {
          page: this.pagination.currentPage,
          per_page: this.pagination.perPage,
          sort_by: this.sortField,
          sort_direction: this.sortDirection,
        };

        // Add search parameter if search query exists
        if (this.searchQuery.trim()) {
          params.search = this.searchQuery.trim();
        }

        const response = await groupsApi.fetchGroups(params);
        this.groups = response.data;
        this.pagination.total = response.total;
        this.pagination.totalPages = response.last_page;
        this.pagination.currentPage = response.current_page;
        this.pagination.from = response.from || 0;
        this.pagination.to = response.to || 0;
        this.totalCount = response.total;
      } catch (error) {
        console.error("Error loading groups:", error);
      } finally {
        this.loading = false;
      }
    },

    handleGroupSelect(group, selected) {
      if (selected) {
        this.selectedGroups.push(group);
      } else {
        this.selectedGroups = this.selectedGroups.filter(
          (g) => g.idgroups !== group.idgroups,
        );
      }
    },

    handleSelectAll(selected) {
      if (selected) {
        this.selectedGroups = [...this.groups];
      } else {
        this.selectedGroups = [];
      }
    },

    clearSelection() {
      this.selectedGroups = [];
    },

    handlePageChange(page) {
      this.pagination.currentPage = page;
      this.loadGroups();
    },

    handlePageSizeChange(newPageSize) {
      this.pagination.perPage = newPageSize;
      this.pagination.currentPage = 1; // Reset to first page when changing page size
      this.loadGroups();
    },

    handleSortChange(field, direction) {
      this.sortField = field;
      this.sortDirection = direction;
      this.loadGroups();
    },

    handleSearchInput() {
      // Clear existing timeout
      if (this.searchTimeout) {
        clearTimeout(this.searchTimeout);
      }

      // Debounce search to avoid too many API calls
      this.searchTimeout = setTimeout(() => {
        this.pagination.currentPage = 1; // Reset to first page when searching
        this.loadGroups();
      }, 500); // 500ms delay
    },

    clearSearch() {
      this.searchQuery = '';
      this.pagination.currentPage = 1;
      this.loadGroups();
    },

    handleAction(group, action) {
      this.confirmationModal = {
        show: true,
        action: action,
        groups: [group],
      };
    },

    handleBulkAction(action) {
      this.confirmationModal = {
        show: true,
        action: action,
        groups: [...this.selectedGroups],
      };
    },

    async handleModalConfirm(data) {
      try {
        this.loading = true;

        if (this.confirmationModal.groups.length === 1) {
          await groupsApi.performAction(
            this.confirmationModal.groups[0].idgroups,
            this.confirmationModal.action,
          );
        } else {
          const groupIds = this.confirmationModal.groups.map((g) => g.idgroups);
          await groupsApi.performBulkActions(groupIds, this.confirmationModal.action);
        }

        this.confirmationModal.show = false;
        this.clearSelection();
        this.loadGroups();
      } catch (error) {
        console.error("Error performing action:", error);
        this.confirmationModal.error = error.response?.data?.message || "Failed to perform action";
      } finally {
        this.loading = false;
      }
    },

    handleModalCancel() {
      this.confirmationModal.show = false;
      this.confirmationModal.action = null;
      this.confirmationModal.groups = [];
    },

    async handleExport() {
      this.exporting = true;

      try {
        const response = await groupsApi.exportGroups();

        // Extract filename from response headers if available
        const contentDisposition = response.headers["content-disposition"];
        let filename = `groups_export_${new Date().toISOString().split("T")[0]}.csv`;

        if (contentDisposition) {
          const filenameMatch = contentDisposition.match(/filename="(.+)"/);
          if (filenameMatch) {
            filename = filenameMatch[1];
          }
        }

        // Create download URL from blob response
        const url = window.URL.createObjectURL(response.data);
        const link = document.createElement("a");
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        this.showAlert("Groups exported successfully!", "success");
      } catch (error) {
        console.error("Export error:", error);
        this.showAlert(
          `Error exporting groups: ${error.response?.data?.message || error.message}`,
          "danger",
        );
      } finally {
        this.exporting = false;
      }
    },

    async handleUpload(file) {
      if (!file) return;

      this.uploadProgress = 0;
      this.uploading = true;

      try {
        const response = await groupsApi.importGroups(
          file,
          (progressEvent) => {
            // Update progress based on actual upload progress
            this.uploadProgress = Math.round(
              (progressEvent.loaded * 100) / progressEvent.total,
            );
          },
        );

        this.showCsvUploadModal = false;

        if (response.success) {
          const { created, errors } = response?.data ?? {};

          if (errors && errors.length > 0) {
            // Partial success - some groups created, some failed
            this.showImportResults({
              type: 'partial',
              created: created || 0,
              errors: errors,
            });
          } else {
            // Complete success
            this.showAlert(response.message, "success");
          }

          this.loadGroups();
        } else {
          // Complete failure
          this.showImportResults({
            type: 'error',
            created: 0,
            errors: [response.message || 'Import failed'],
          });
        }
      } catch (error) {
        console.error("CSV Upload Error:", error);
        this.showCsvUploadModal = false;

        // Extract meaningful error message from response
        let errorMessage = 'Upload failed. Please try again.';
        let errors = [];

        if (error.response?.data) {
          errorMessage = error.response.data.message || errorMessage;

          // Check if there are detailed errors in the response
          if (error.response.data.errors) {
            if (Array.isArray(error.response.data.errors)) {
              errors = error.response.data.errors;
            } else if (typeof error.response.data.errors === 'object') {
              // Laravel validation errors format
              errors = Object.values(error.response.data.errors).flat();
            }
          }
        } else if (error.message) {
          errorMessage = error.message;
        }

        if (errors.length > 0) {
          this.showImportResults({
            type: 'error',
            created: 0,
            errors: errors,
          });
        } else {
          this.showAlert(errorMessage, "danger");
        }
      } finally {
        this.uploading = false;
        this.uploadProgress = 0;
      }
    },

    clearAlert() {
      this.alert.message = "";
      this.alert.type = "success";
    },

    showAlert(message, type) {
      this.alert.message = message;
      this.alert.type = type;
    },

    showImportResults(results) {
      this.importResults = {
        show: true,
        type: results.type,
        created: results.created,
        errors: results.errors || [],
      };
    },

    closeImportResults() {
      this.importResults.show = false;
    },

    handleScroll() {
      // Show scroll-to-top button when user scrolls down more than 300px
      this.showScrollToTop = window.scrollY > 300;
    },

    scrollToTop() {
      // Smooth scroll to top of page
      window.scrollTo({
        top: 0,
        behavior: "smooth",
      });
    },
  },
};
</script>

<style scoped>
.groups-management {
  padding: 20px;
}

.scroll-to-top-btn {
  box-shadow: 5px 5px 0 0 #222;
  border: solid 1px #222;
  padding: 10px;
  border-radius: 0;
  position: fixed;
  bottom: 30px;
  right: 30px;
  width: 50px;
  height: 50px;
  background-color: white;
  color: #222;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  opacity: 0;
  visibility: hidden;
  transform: translateY(20px);
  z-index: 1000;
}

.scroll-to-top-btn:hover {
  background-color: white;
  transform: translateY(0);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.scroll-to-top-btn.show {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}

.scroll-to-top-btn .chevron-up {
  font-size: 16px;
  font-weight: bold;
  line-height: 1;
}

.form-control:focus {
  border-color: #80bdff;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.input-group-append {
  background-color: #f8f9fa;
  border-color: #ced4da;
  color: #6c757d;
  height: 40px;
}
</style>
