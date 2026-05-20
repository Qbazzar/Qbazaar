// Bazzar — Profile / My Ads dashboard
const Profile = ({ listings, savedIds, onSave }) => {
  const [tab, setTab] = useState("active");
  const myAds = listings.slice(0, 6);
  const tabs = [
    { id: "active",  label: "Active",   count: myAds.length },
    { id: "sold",    label: "Sold",     count: 14 },
    { id: "drafts",  label: "Drafts",   count: 1 },
    { id: "expired", label: "Expired",  count: 3 },
  ];

  return (
    <div className="container" style={{ padding: "32px 24px 0" }}>
      {/* Profile header */}
      <div style={{ display: "flex", gap: 32, alignItems: "center", padding: "32px 36px", background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 20, marginBottom: 32, flexWrap: "wrap" }}>
        <div style={{ width: 96, height: 96, borderRadius: 999, background: "var(--cream-200)", display: "flex", alignItems: "center", justifyContent: "center", fontFamily: "'Instrument Serif', serif", fontSize: 44, color: "var(--terracotta)", flexShrink: 0 }}>S</div>
        <div style={{ flex: 1, minWidth: 240 }}>
          <h1 style={{ margin: 0, fontFamily: "'Instrument Serif', serif", fontSize: "clamp(28px, 3vw, 36px)", fontWeight: 400, color: "var(--ink-900)" }}>Sara Al-Mansoori</h1>
          <div style={{ fontFamily: "var(--font-ui)", fontSize: 13.5, color: "var(--ink-500)", marginTop: 4, display: "flex", gap: 14, flexWrap: "wrap" }}>
            <span>Member since March 2022</span>
            <span>·</span>
            <span style={{ display: "inline-flex", gap: 4, alignItems: "center" }}><Icon name="location" size={13}/>Doha, Qatar</span>
            <span>·</span>
            <span style={{ display: "inline-flex", gap: 4, alignItems: "center" }}>
              <Icon name="star" size={13} stroke={0}/>4.9 (38 reviews)
            </span>
          </div>
          <div style={{ display: "flex", gap: 8, marginTop: 12, flexWrap: "wrap" }}>
            <Pill kind="sage">✓ ID verified</Pill>
            <Pill kind="sage">✓ Phone verified</Pill>
            <Pill kind="sage">✓ Email verified</Pill>
          </div>
        </div>
        <div style={{ display: "flex", gap: 10 }}>
          <Btn kind="ghost" icon="user">Edit profile</Btn>
          <Btn kind="primary" icon="plus" href="#/post">Post new ad</Btn>
        </div>
      </div>

      {/* Stats */}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 12, marginBottom: 32 }}>
        {[
          { label: "Active ads",     value: "6",       sub: "1 featured" },
          { label: "Total views",    value: "12,481",  sub: "+18% this month" },
          { label: "Messages",       value: "23",      sub: "5 unread" },
          { label: "Saved ads",      value: savedIds.length || "8", sub: "across 4 categories" },
          { label: "Items sold",     value: "14",      sub: "since joining" },
        ].map(s => (
          <div key={s.label} style={{ background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 14, padding: "16px 18px" }}>
            <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, color: "var(--ink-500)", letterSpacing: "0.04em" }}>{s.label}</div>
            <div style={{ fontFamily: "'Instrument Serif', serif", fontSize: 32, color: "var(--ink-900)", marginTop: 4 }}>{s.value}</div>
            <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, color: "var(--coral)", marginTop: 2 }}>{s.sub}</div>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <div style={{ borderBottom: "1px solid var(--ink-200)", display: "flex", gap: 4, marginBottom: 20, overflowX: "auto" }}>
        {tabs.map(t => (
          <button key={t.id} onClick={()=>setTab(t.id)} style={{
            background: "transparent", border: "none", padding: "12px 16px",
            fontFamily: "var(--font-ui)", fontSize: 14, fontWeight: 600,
            color: tab === t.id ? "var(--ink-900)" : "var(--ink-500)",
            borderBottom: `2px solid ${tab === t.id ? "var(--coral)" : "transparent"}`,
            cursor: "pointer", whiteSpace: "nowrap",
          }}>
            {t.label} <span style={{ color: "var(--ink-500)", fontWeight: 400 }}>({t.count})</span>
          </button>
        ))}
      </div>

      {/* Ads list */}
      <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
        {myAds.map(l => (
          <div key={l.id} style={{ display: "grid", gridTemplateColumns: "120px 1fr auto", gap: 18, padding: 14, background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 14, alignItems: "center" }}>
            <div style={{ width: 120, height: 90, overflow: "hidden", borderRadius: 8 }}>
              <SwatchImg swatch={l.swatch} aspect="4 / 3" idx={Number(l.id.slice(-1))} photo={l.photo}/>
            </div>
            <div>
              <div style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 4 }}>
                {l.featured && <Pill kind="featured">Featured</Pill>}
                <span style={{ fontFamily: "var(--font-ui)", fontSize: 11, color: "var(--ink-500)" }}>Ad {l.id}</span>
              </div>
              <a href={`#/detail/${l.id}`} style={{ fontFamily: "var(--font-ui)", fontSize: 15, fontWeight: 600, color: "var(--ink-900)", textDecoration: "none", display: "block", marginBottom: 6 }}>{l.title}</a>
              <div style={{ display: "flex", gap: 16, fontFamily: "var(--font-ui)", fontSize: 12.5, color: "var(--ink-500)" }}>
                <span style={{ fontFamily: "'Instrument Serif', serif", color: "var(--terracotta)", fontSize: 18 }}>{formatPrice(l.price)} QAR</span>
                <span style={{ display: "inline-flex", alignItems: "center", gap: 4 }}><Icon name="eye" size={12}/> {(800 + Number(l.id.slice(-2)) * 47).toLocaleString()} views</span>
                <span style={{ display: "inline-flex", alignItems: "center", gap: 4 }}><Icon name="chat" size={12}/> 12 messages</span>
                <span style={{ display: "inline-flex", alignItems: "center", gap: 4 }}><Icon name="heart" size={12}/> 38 saved</span>
              </div>
            </div>
            <div style={{ display: "flex", gap: 6 }}>
              <Btn kind="soft" size="sm">Edit</Btn>
              <Btn kind="ghost" size="sm" icon="star">Boost</Btn>
              <button style={{ width: 36, height: 36, borderRadius: 8, background: "var(--cream-100)", border: "1px solid var(--ink-200)", color: "var(--ink-700)", cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center" }}>···</button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

window.Profile = Profile;
