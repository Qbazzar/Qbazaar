// Bazzar — Search / Category Results page
const Search = ({ listings, categories, cities, savedIds, onSave }) => {
  const [layout, setLayout] = useState("grid");
  const [sortBy, setSortBy] = useState("Newest");
  const [activeCat, setActiveCat] = useState(null);
  const [priceMin, setPriceMin] = useState("");
  const [priceMax, setPriceMax] = useState("");
  const [city, setCity] = useState("All Qatar");
  const [condition, setCondition] = useState([]);
  const filtered = listings.filter(l => !activeCat || l.category === activeCat);
  return (
    <div className="container" style={{ padding: "32px 24px 0" }}>
      {/* Breadcrumbs */}
      <div style={{ display: "flex", alignItems: "center", gap: 8, fontFamily: "var(--font-ui)", fontSize: 12.5, color: "var(--ink-500)", marginBottom: 18 }}>
        <a href="#/home" style={{ color: "var(--ink-500)", textDecoration: "none" }}>Home</a>
        <Icon name="chevron" size={12}/>
        <span>Browse</span>
        {activeCat && (<>
          <Icon name="chevron" size={12}/>
          <span style={{ color: "var(--ink-900)" }}>{categories.find(c=>c.id===activeCat)?.label}</span>
        </>)}
      </div>

      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-end", gap: 16, marginBottom: 24, flexWrap: "wrap" }}>
        <h1 style={{ margin: 0, fontFamily: "'Instrument Serif', serif", fontSize: "clamp(36px, 4vw, 52px)", fontWeight: 400, color: "var(--ink-900)", letterSpacing: "-0.015em", lineHeight: 1 }}>
          {activeCat ? categories.find(c=>c.id===activeCat)?.label : "All listings"}
        </h1>
        <div style={{ fontFamily: "var(--font-ui)", color: "var(--ink-700)", fontSize: 14 }}>{filtered.length.toLocaleString()} results in <strong>{city}</strong></div>
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "280px 1fr", gap: 32 }}>
        {/* FILTERS SIDEBAR */}
        <aside className="desktop-only" style={{ position: "sticky", top: 100, alignSelf: "flex-start", maxHeight: "calc(100vh - 120px)", overflowY: "auto" }}>
          <FilterGroup title="Category">
            <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>
              <button onClick={()=>setActiveCat(null)} style={filterRow(!activeCat)}>
                All categories <span style={{ color: "var(--ink-500)", fontSize: 12 }}>{listings.length}</span>
              </button>
              {categories.slice(0, 10).map(c => (
                <button key={c.id} onClick={()=>setActiveCat(c.id)} style={filterRow(activeCat === c.id)}>
                  <span style={{ display: "inline-flex", alignItems: "center", gap: 8 }}><Icon name={c.icon} size={14}/>{c.label}</span>
                  <span style={{ color: "var(--ink-500)", fontSize: 12 }}>{c.count}</span>
                </button>
              ))}
            </div>
          </FilterGroup>

          <FilterGroup title="Price (QAR)">
            <div style={{ display: "flex", gap: 8 }}>
              <Input placeholder="Min" value={priceMin} onChange={e=>setPriceMin(e.target.value)}/>
              <Input placeholder="Max" value={priceMax} onChange={e=>setPriceMax(e.target.value)}/>
            </div>
            <div style={{ display: "flex", gap: 6, marginTop: 10, flexWrap: "wrap" }}>
              {["< 500", "500–2k", "2k–10k", "10k+"].map(r => <Chip key={r}>{r}</Chip>)}
            </div>
          </FilterGroup>

          <FilterGroup title="City">
            <select value={city} onChange={e=>setCity(e.target.value)}
              style={{ width: "100%", padding: "11px 14px", border: "1px solid var(--ink-200)", borderRadius: 10, background: "#fff", fontFamily: "var(--font-ui)", fontSize: 14 }}>
              <option>All Qatar</option>
              {cities.map(c => <option key={c}>{c}</option>)}
            </select>
          </FilterGroup>

          <FilterGroup title="Condition">
            {["New", "Like New", "Used", "For Parts"].map(c => (
              <label key={c} style={{ display: "flex", gap: 10, alignItems: "center", padding: "6px 0", fontFamily: "var(--font-ui)", fontSize: 14, color: "var(--ink-900)", cursor: "pointer" }}>
                <input type="checkbox" checked={condition.includes(c)} onChange={()=>{
                  setCondition(condition.includes(c) ? condition.filter(x=>x!==c) : [...condition, c]);
                }} style={{ accentColor: "var(--coral)" }}/>
                {c}
              </label>
            ))}
          </FilterGroup>

          <FilterGroup title="Posted within">
            {["Last hour", "Today", "This week", "This month"].map(c => (
              <label key={c} style={{ display: "flex", gap: 10, alignItems: "center", padding: "6px 0", fontFamily: "var(--font-ui)", fontSize: 14, color: "var(--ink-900)", cursor: "pointer" }}>
                <input type="radio" name="posted" style={{ accentColor: "var(--coral)" }}/>
                {c}
              </label>
            ))}
          </FilterGroup>

          <Btn kind="ghost" size="sm" full>Clear all filters</Btn>
        </aside>

        {/* RESULTS */}
        <div>
          {/* Toolbar */}
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "12px 16px", background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 12, marginBottom: 20, gap: 12, flexWrap: "wrap" }}>
            <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
              {["All Qatar", "Posted today", "With photos", "Featured"].map(t => (
                <Chip key={t}>{t}</Chip>
              ))}
            </div>
            <div style={{ display: "flex", gap: 12, alignItems: "center" }}>
              <span style={{ fontFamily: "var(--font-ui)", fontSize: 13, color: "var(--ink-500)" }}>Sort by</span>
              <select value={sortBy} onChange={e=>setSortBy(e.target.value)}
                style={{ padding: "7px 10px", border: "1px solid var(--ink-200)", borderRadius: 8, fontFamily: "var(--font-ui)", fontSize: 13 }}>
                <option>Newest</option>
                <option>Price: low to high</option>
                <option>Price: high to low</option>
                <option>Most viewed</option>
              </select>
              <div style={{ display: "flex", border: "1px solid var(--ink-200)", borderRadius: 8, overflow: "hidden" }}>
                <button onClick={()=>setLayout("grid")} style={layoutBtn(layout==="grid")}><Icon name="grid" size={16}/></button>
                <button onClick={()=>setLayout("list")} style={layoutBtn(layout==="list")}><Icon name="list" size={16}/></button>
              </div>
            </div>
          </div>

          {/* Results */}
          <div className={layout === "grid" ? "grid-3" : ""} style={layout === "list" ? { display: "flex", flexDirection: "column", gap: 14 } : {}}>
            {filtered.map(l => (
              <ListingCard key={l.id} listing={l} layout={layout} onSave={onSave} saved={savedIds.includes(l.id)}/>
            ))}
          </div>

          {/* Pagination */}
          <div style={{ marginTop: 40, display: "flex", justifyContent: "center", gap: 8, fontFamily: "var(--font-ui)" }}>
            <Btn kind="ghost" size="sm" icon="back">Prev</Btn>
            {[1, 2, 3, 4, 5].map(n => (
              <button key={n} style={{ width: 38, height: 38, borderRadius: 8, border: "1px solid var(--ink-200)", background: n===1?"var(--coral)":"#fff", color: n===1?"#fff":"var(--ink-900)", cursor: "pointer", fontWeight: 600 }}>{n}</button>
            ))}
            <span style={{ alignSelf: "center", padding: "0 8px", color: "var(--ink-500)" }}>… 234</span>
            <Btn kind="ghost" size="sm" iconRight="chevron">Next</Btn>
          </div>
        </div>
      </div>
    </div>
  );
};

const FilterGroup = ({ title, children }) => (
  <div style={{ paddingBottom: 24, marginBottom: 24, borderBottom: "1px solid var(--ink-200)" }}>
    <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, fontWeight: 700, letterSpacing: "0.1em", color: "var(--ink-900)", textTransform: "uppercase", marginBottom: 12 }}>{title}</div>
    {children}
  </div>
);

const filterRow = (active) => ({
  display: "flex", justifyContent: "space-between", alignItems: "center",
  padding: "8px 10px", border: "none", background: active ? "var(--cream-200)" : "transparent",
  fontFamily: "var(--font-ui)", fontSize: 13.5, color: "var(--ink-900)", borderRadius: 8,
  cursor: "pointer", width: "100%", textAlign: "left", fontWeight: active ? 600 : 500,
});

const layoutBtn = (active) => ({
  padding: "7px 10px", background: active ? "var(--cream-200)" : "#fff",
  border: "none", color: active ? "var(--terracotta)" : "var(--ink-500)", cursor: "pointer",
});

window.Search = Search;
