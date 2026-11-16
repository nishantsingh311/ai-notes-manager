import { Plus } from "lucide-react";

export default function Header({ onCreate }) {
    return <header className="topbar"><div><p className="eyebrow">YOUR WORKSPACE</p><h1>My Notes</h1></div><button className="primary-button" onClick={onCreate}><Plus size={18} />New Note</button></header>;
}
