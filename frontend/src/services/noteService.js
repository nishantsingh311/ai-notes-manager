import axios from "axios";

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL,
    headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
    },
});

const noteService = {
    getStats: () => api.get("/notes/stats"),
    
    getNotes: (page = 1, limit = 10) =>
        api.get(`/notes?page=${page}&limit=${limit}`),

    getNote: (id) => api.get(`/notes/${id}`),

    createNote: (note) => api.post("/notes", note),

    updateNote: (id, note) => api.put(`/notes/${id}`, note),

    deleteNote: (id) => api.delete(`/notes/${id}`),

    searchNotes: (query, limit = 10) =>
        api.get("/notes/search", {
            params: { q: query, limit },
        }),

    generateSummary: (id) => api.post(`/notes/${id}/summary`),
};

export default noteService;