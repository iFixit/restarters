<template>
    <div class="groups-management">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Groups Management</h2>
      </div>

      <GroupsTable
        :groups="groups"
        :loading="loading"
        :selected-groups="selectedGroups"
        :pagination="pagination"
        :sort-field="sortField"
        :sort-direction="sortDirection"
        @select="handleGroupSelect"
        @select-all="handleSelectAll"
        @page-change="handlePageChange"
        @sort-change="handleSortChange"
      />

    </div>
</template>
  
<script>
import GroupsTable from "./GroupsTable.vue";
import groupsApi from "../../api/groups.js";

export default {
	name: "GroupsManagement",
	components: {
		GroupsTable,
	},

	data() {
		return {
			groups: [],
			loading: false,
			selectedGroups: [],
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
	},
};
</script>
  
<style scoped>
  .groups-management {
    padding: 20px;
  }
</style> 
