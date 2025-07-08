<template>
  <div class="groups-management">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Groups Management</h2>
    </div>

    <GroupsBulkAction :selected-groups="selectedGroups" :total-count="totalCount" @action="handleBulkAction"
      @clear-selection="clearSelection" />

    <GroupsTable :groups="groups" :loading="loading" :selected-groups="selectedGroups" :pagination="pagination"
      :sort-field="sortField" :sort-direction="sortDirection" @action="handleAction" @select="handleGroupSelect"
      @select-all="handleSelectAll" @page-change="handlePageChange" @sort-change="handleSortChange" />

    <ConfirmationModal :show="confirmationModal.show" :action="confirmationModal.action"
      :groups="confirmationModal.groups" :error="confirmationModal.error" @confirm="handleModalConfirm"
      @cancel="handleModalCancel" />

  </div>
</template>

<script>
import GroupsTable from "./GroupsTable.vue";
import GroupsBulkAction from "./GroupsBulkAction.vue";
import ConfirmationModal from "./ConfirmationModal.vue";
import groupsApi from "../../api/groups.js";

export default {
  name: "GroupsManagement",
  components: {
    GroupsTable,
    GroupsBulkAction,
    ConfirmationModal,
  },

  data() {
    return {
      groups: [],
      loading: false,
      selectedGroups: [],
      totalCount: 0,

      confirmationModal: {
        show: false,
        action: null,
        groups: [],
        error: null,
      },


      pagination: {
        currentPage: 1,
        perPage: 25,
        totalPages: 0,
        total: 0,
      },
      sortField: "name",
      sortDirection: "asc",
    };
  },

  mounted() {
    this.loadGroups();
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

        const response = await groupsApi.fetchGroups(params);
        this.groups = response.data;
        this.pagination.total = response.total;
        this.pagination.totalPages = response.last_page;
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

    handleSortChange(field, direction) {
      this.sortField = field;
      this.sortDirection = direction;
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
  },
};
</script>

<style scoped>
.groups-management {
  padding: 20px;
}
</style>
