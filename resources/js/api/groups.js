import axios from "axios";

const API_BASE = "/api/v2/admin/groups";

export default {
	// Fetch groups with pagination and filtering
	async fetchGroups(params = {}) {
		const response = await axios.get(API_BASE, { params });
		return response.data;
	},

	// Perform single action on a group
	async performAction(groupId, action) {
		const response = await axios.post(`${API_BASE}/${groupId}/${action}`);
		return response.data;
	},
};
