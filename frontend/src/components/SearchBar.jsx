import { Search, X } from "lucide-react";

export default function SearchBar({ value, onChange, onSearch, onClear, searching }) {
    return <form className="search-bar" onSubmit={onSearch}><Search size={19} /><input type="text" placeholder="Search notes by meaning... e.g. buying a car" value={value} onChange={(event) => onChange(event.target.value)} />{value && <button type="button" className="clear-search" onClick={onClear} aria-label="Clear search"><X size={17} /></button>}<button className="search-button" type="submit" disabled={searching}>{searching ? "Searching..." : "Search"}</button></form>;
}
