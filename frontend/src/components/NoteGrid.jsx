import { FileText, LoaderCircle, Plus } from "lucide-react";
import NoteCard from "./NoteCard";

export default function NoteGrid({ notes, loading, search, searchActive = false, onCreate, onOpen, onEdit, onDelete, onSummary }) {
    if (loading) return <div className="loading-state"><LoaderCircle className="spin" size={26} /><span>Loading your notes...</span></div>;
    if (!notes.length) return <div className="empty-state"><div className="empty-icon"><FileText size={30} /></div><h3>No notes found</h3><p>{search ? "Try another search phrase." : "Create your first note to get started."}</p>{!search && <button className="primary-button" onClick={onCreate}><Plus size={17} />Create Note</button>}</div>;
    return <div className="notes-grid">{notes.map((note, index) => <NoteCard key={note.id} note={note} searchMatch={searchActive && index === 0} onOpen={() => onOpen(note)} onEdit={onEdit} onDelete={onDelete} onSummary={onSummary} />)}</div>;
}
