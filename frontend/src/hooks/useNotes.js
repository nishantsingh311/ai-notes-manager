import { useCallback, useState } from "react";
import noteService from "../services/noteService";

const unwrap = (value) => {
    let result = value;
    while (result && !Array.isArray(result) && result.data !== undefined) result = result.data;
    return result;
};

export default function useNotes() {
    const [notes, setNotes] = useState([]);
    const [note, setNote] = useState(null);
    const [stats, setStats] = useState({ total_notes: 0, ai_summaries: 0, recent_notes: 0 });
    const [pagination, setPagination] = useState(null);
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(false);
    const [searching, setSearching] = useState(false);
    const [error, setError] = useState("");

    const loadNotes = useCallback(async (currentPage = 1) => {
        setLoading(true);
        setError("");
        try {
            const response = await noteService.getNotes(currentPage, 10);
            let result = response.data?.data ?? response.data;
            while (result && !Array.isArray(result) && !result.current_page && result.data !== undefined) result = result.data;
            const paged = result && !Array.isArray(result) && result.current_page;
            setNotes(paged ? (Array.isArray(result.data) ? result.data : []) : (Array.isArray(result) ? result : []));
            setPagination(paged ? result : null);
            setPage(currentPage);
            try {
                const statsResponse = await noteService.getStats();
                const data = statsResponse.data?.data ?? {};
                setStats({ total_notes: data.total_notes ?? 0, ai_summaries: data.ai_summaries ?? 0, recent_notes: data.recent_notes ?? 0 });
            } catch (statsError) {
                console.error("Statistics API Error:", statsError);
            }
        } catch (err) {
            console.error("Load Notes Error:", err);
            setError(err.response?.data?.message || "Could not load notes.");
            setNotes([]);
        } finally { setLoading(false); }
    }, []);

    const searchNotes = useCallback(async (query) => {
        if (!query.trim()) {
            await loadNotes(1);
            return false;
        }
        setSearching(true); setError("");
        try {
            const response = await noteService.searchNotes(query.trim(), 10);
            setNotes((response.data.data || []).map((item) => item.note || item));
            setPagination(null);
            return true;
        } catch (err) { setError(err.response?.data?.message || "Search failed."); return false; }
        finally { setSearching(false); }
    }, [loadNotes]);

    const getNote = useCallback(async (id) => {
        setLoading(true); setError("");
        try { setNote(unwrap((await noteService.getNote(id)).data)); }
        catch (err) { console.error("Get Note Error:", err); setError(err.response?.data?.message || "Unable to load this note."); }
        finally { setLoading(false); }
    }, []);

    const saveNote = useCallback((id, data) => id ? noteService.updateNote(id, data) : noteService.createNote(data), []);
    const deleteNote = useCallback((id) => noteService.deleteNote(id), []);

    const generateSummary = useCallback(async (id) => {
        const response = await noteService.generateSummary(id);
        const result = response.data.data || response.data;
        const summary = result.summary || result.note?.summary || response.data.summary || "No summary was returned.";
        setNote((current) => current ? { ...current, summary: result.summary || result.data?.summary || result.note?.summary || result.data?.note?.summary || current.summary } : current);
        return summary;
    }, []);

    return { notes, setNotes, note, setNote, stats, pagination, page, loading, searching, error, setError, loadNotes, searchNotes, getNote, saveNote, deleteNote, generateSummary };
}
