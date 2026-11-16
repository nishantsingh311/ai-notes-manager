import { useEffect, useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { Sparkles } from "lucide-react";
import useNotes from "../hooks/useNotes";
import Sidebar from "../components/Sidebar";
import Header from "../components/Header";
import StatsCards from "../components/StatsCards";
import SearchBar from "../components/SearchBar";
import NoteGrid from "../components/NoteGrid";
import NoteModal from "../components/NoteModal";
import SummaryModal from "../components/SummaryModal";
import ConfirmDialog from "../components/ConfirmDialog";

function Dashboard() {
    const navigate = useNavigate();
    const location = useLocation();
    const { notes, setNotes, stats, pagination, page, loading, searching, error, setError, loadNotes, searchNotes, saveNote, deleteNote, generateSummary } = useNotes();
    const [search, setSearch] = useState("");
    const [searchActive, setSearchActive] = useState(false);
    const [saving, setSaving] = useState(false);
    const [editingNote, setEditingNote] = useState(null);
    const [editorOpen, setEditorOpen] = useState(false);
    const [summaryOpen, setSummaryOpen] = useState(false);
    const [summary, setSummary] = useState("");
    const [summaryLoading, setSummaryLoading] = useState(false);
    const [pendingDelete, setPendingDelete] = useState(null);

    useEffect(() => { loadNotes(1); }, [loadNotes]);

    const routeEditNote = notes.find((item) => String(item.id) === String(location.state?.editNoteId));
    const activeEditingNote = editingNote || routeEditNote;
    const isEditorOpen = editorOpen || Boolean(routeEditNote);

    const openCreate = () => { setEditingNote(null); setError(""); setEditorOpen(true); };
    const openEdit = (note) => { setEditingNote(note); setError(""); setEditorOpen(true); };
    const closeEditor = () => {
        setEditorOpen(false); setError("");
        if (location.state?.returnTo) navigate(location.state.returnTo, { replace: true, state: null });
    };
    const handleSave = async (form) => {
        if (!form.title.trim() || !form.content.trim()) { setError("Please enter both title and content."); return; }
        try {
            setSaving(true); setError("");
            await saveNote(activeEditingNote?.id, form);
            setEditorOpen(false); setSearch("");
            setSearchActive(false);
            const returnTo = location.state?.returnTo || "/";
            navigate(returnTo, { replace: true, state: null });
            await loadNotes(1);
        } catch (err) { setError(err.response?.data?.message || "Could not save the note. Please check the form."); }
        finally { setSaving(false); }
    };
    const handleDelete = async () => {
        try { await deleteNote(pendingDelete.id); setNotes((current) => current.filter((item) => item.id !== pendingDelete.id)); }
        catch (err) { setError(err.response?.data?.message || "Could not delete the note."); }
        finally { setPendingDelete(null); }
    };
    const handleSummary = async (note) => {
        setSummaryOpen(true); setSummaryLoading(true); setSummary("");
        try { setSummary(await generateSummary(note.id)); await loadNotes(page); }
        catch (err) { setSummary(err.response?.data?.message || "Unable to generate the summary."); }
        finally { setSummaryLoading(false); }
    };
    const handleSearch = async (event) => { event.preventDefault(); setSearchActive(false); const matched = await searchNotes(search); setSearchActive(matched); };
    const clearSearch = () => { setSearch(""); setSearchActive(false); loadNotes(1); };

    return <div className="dashboard"><Sidebar /><main className="main-content"><Header onCreate={openCreate} /><section className="welcome-section"><div><h2>Your ideas, all in one place.</h2><p>Create notes, search by meaning, and generate AI summaries.</p></div><div className="welcome-icon"><Sparkles size={32} /></div></section><StatsCards stats={stats} /><section className="notes-section"><div className="section-heading"><div><h2>All Notes</h2><p>Manage and explore your notes</p></div></div><SearchBar value={search} onChange={setSearch} onSearch={handleSearch} onClear={clearSearch} searching={searching} />{error && <div className="error-message">{error}</div>}<NoteGrid notes={notes} loading={loading} search={search} searchActive={searchActive} onCreate={openCreate} onOpen={(note) => navigate(`/notes/${note.id}`)} onEdit={openEdit} onDelete={setPendingDelete} onSummary={handleSummary} />{pagination?.last_page > 1 && !search && <div className="pagination"><button disabled={page <= 1} onClick={() => loadNotes(page - 1)}>Previous</button><span>Page {page} of {pagination.last_page}</span><button disabled={page >= pagination.last_page} onClick={() => loadNotes(page + 1)}>Next</button></div>}</section></main>{isEditorOpen && <NoteModal key={activeEditingNote?.id ?? "new"} note={activeEditingNote} saving={saving} error={error} onClose={closeEditor} onSave={handleSave} />}{summaryOpen && <SummaryModal summary={summary} loading={summaryLoading} onClose={() => setSummaryOpen(false)} />}<ConfirmDialog open={Boolean(pendingDelete)} onCancel={() => setPendingDelete(null)} onConfirm={handleDelete} /></div>;
}

export default Dashboard;
