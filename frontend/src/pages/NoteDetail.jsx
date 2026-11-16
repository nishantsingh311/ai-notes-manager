import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { ArrowLeft, CalendarDays, Clock, FileText, LoaderCircle, Pencil, Sparkles, Trash2 } from "lucide-react";
import useNotes from "../hooks/useNotes";
import NoteModal from "../components/NoteModal";
import ConfirmDialog from "../components/ConfirmDialog";
import "./NoteDetail.css";

function NoteDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { note, loading, error, setError, getNote, saveNote, deleteNote, generateSummary } = useNotes();
    const [summaryLoading, setSummaryLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [editorOpen, setEditorOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [modalError, setModalError] = useState("");

    useEffect(() => { getNote(id); }, [getNote, id]);

    const handleSummary = async () => {
        try { setSummaryLoading(true); setError(""); await generateSummary(id); }
        catch (err) { console.error("Summary Error:", err); setError(err.response?.data?.message || "Unable to generate summary."); }
        finally { setSummaryLoading(false); }
    };
    const handleDelete = async () => {
        try { await deleteNote(id); navigate("/"); }
        catch (err) { setError(err.response?.data?.message || "Unable to delete this note."); }
        finally { setConfirmDelete(false); }
    };
    const handleSave = async (form) => {
        if (!form.title.trim() || !form.content.trim()) { setModalError("Please enter both title and content."); return; }
        try { setSaving(true); setModalError(""); await saveNote(id, form); setEditorOpen(false); await getNote(id); }
        catch (err) { setModalError(err.response?.data?.message || "Could not save the note. Please check the form."); }
        finally { setSaving(false); }
    };

    if (loading) return <div className="detail-state"><LoaderCircle className="spin" size={30} /><p>Loading your note...</p></div>;
    if (error && !note) return <div className="detail-state"><FileText size={35} /><h2>Note not available</h2><p>{error}</p><button className="detail-primary" onClick={() => navigate("/")}><ArrowLeft size={17} />Back to Notes</button></div>;
    if (!note || typeof note !== "object" || !note.id) return <div className="detail-state"><h2>Note not found</h2><button className="detail-primary" onClick={() => navigate("/")}><ArrowLeft size={17} />Back to Notes</button></div>;

    const createdDate = note.created_at ? new Date(note.created_at).toLocaleString() : "Not available";
    const updatedDate = note.updated_at ? new Date(note.updated_at).toLocaleString() : "Not available";
    return <div className="detail-page"><header className="detail-topbar"><button className="back-button" onClick={() => navigate("/")}><ArrowLeft size={18} />Back to Notes</button><div className="detail-actions"><button className="detail-edit" onClick={() => { setModalError(""); setEditorOpen(true); }}><Pencil size={16} />Edit</button><button className="detail-delete" onClick={() => setConfirmDelete(true)}><Trash2 size={16} />Delete</button></div></header>{error && <div className="detail-error">{error}</div>}<div className="detail-layout"><main className="note-document"><div className="document-category"><FileText size={16} /><span>MY NOTES</span></div><h1>{note.title}</h1><div className="document-meta"><span><CalendarDays size={15} />Created: {createdDate}</span><span><Clock size={15} />Updated: {updatedDate}</span></div><div className="document-divider" /><article className="document-content">{note.content}</article></main><aside className="summary-panel"><div className="summary-panel-heading"><div className="summary-panel-icon"><Sparkles size={19} /></div><div><h2>AI Summary</h2><p>Quick overview of your note</p></div></div>{note.summary ? <div className="detail-summary-text">{note.summary}</div> : <div className="no-summary"><Sparkles size={25} /><p>No summary generated yet.</p><span>Generate a summary to understand the key points.</span></div>}<button className="generate-summary-button" onClick={handleSummary} disabled={summaryLoading}>{summaryLoading ? <><LoaderCircle className="spin" size={17} />Generating...</> : <><Sparkles size={17} />{note.summary ? "Regenerate Summary" : "Generate Summary"}</>}</button><div className="summary-note"><Sparkles size={14} />AI-powered note assistance</div></aside></div>{editorOpen && <NoteModal note={note} saving={saving} error={modalError} onClose={() => setEditorOpen(false)} onSave={handleSave} />}<ConfirmDialog open={confirmDelete} onCancel={() => setConfirmDelete(false)} onConfirm={handleDelete} /></div>;
}

export default NoteDetail;
