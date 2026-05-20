// Bazzar — Help / FAQ
const Help = ({ faq }) => {
  const [open, setOpen] = useState(0);
  const [query, setQuery] = useState("");
  const filtered = faq.filter(f => !query || f.q.toLowerCase().includes(query.toLowerCase()) || f.a.toLowerCase().includes(query.toLowerCase()));
  const topics = [
    { id: "buying",   icon: "cart",      title: "Buying on Bazzar",       count: 12 },
    { id: "selling",  icon: "package",   title: "Selling & posting ads",  count: 18 },
    { id: "account",  icon: "user",      title: "Account & profile",      count: 9 },
    { id: "safety",   icon: "shield",    title: "Trust & safety",         count: 14 },
    { id: "payments", icon: "star",      title: "Payments & boosts",      count: 7 },
    { id: "report",   icon: "flag",      title: "Reporting & disputes",   count: 6 },
  ];
  return (
    <div className="container" style={{ padding: "32px 24px 0" }}>
      {/* Search hero */}
      <div style={{ textAlign: "center", padding: "32px 24px 48px" }}>
        <h1 style={{ margin: 0, fontFamily: "'Instrument Serif', serif", fontSize: "clamp(40px, 5vw, 60px)", fontWeight: 400, color: "var(--ink-900)", letterSpacing: "-0.02em" }}>
          How can we help?
        </h1>
        <p style={{ margin: "12px auto 0", fontFamily: "var(--font-ui)", fontSize: 16, color: "var(--ink-700)", maxWidth: 540 }}>
          Search our help centre or pick a topic below. Most questions have answers in under 30 seconds.
        </p>
        <div style={{ margin: "32px auto 0", maxWidth: 580, display: "flex", alignItems: "center", background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 14, padding: 6, boxShadow: "0 10px 30px rgba(42,38,34,0.06)" }}>
          <div style={{ paddingLeft: 14, color: "var(--ink-500)" }}><Icon name="search"/></div>
          <input value={query} onChange={e=>setQuery(e.target.value)} placeholder="Search 'how to post', 'safe meet up', 'refund'…"
            style={{ flex: 1, border: "none", outline: "none", padding: "14px", fontFamily: "var(--font-ui)", fontSize: 15 }}/>
          <Btn kind="primary" size="md">Search</Btn>
        </div>
      </div>

      {/* Topics */}
      <section style={{ paddingTop: 24 }}>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 14 }}>
          {topics.map(t => (
            <a key={t.id} href="#" style={{ display: "flex", gap: 14, padding: 20, background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 14, textDecoration: "none", color: "inherit", transition: "border-color .15s, transform .15s" }}
              onMouseEnter={(e)=>{ e.currentTarget.style.borderColor="var(--coral)"; e.currentTarget.style.transform="translateY(-2px)"; }}
              onMouseLeave={(e)=>{ e.currentTarget.style.borderColor="var(--ink-200)"; e.currentTarget.style.transform="translateY(0)"; }}>
              <div style={{ width: 44, height: 44, borderRadius: 10, background: "var(--cream-200)", color: "var(--terracotta)", display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0 }}>
                <Icon name={t.icon} size={22}/>
              </div>
              <div>
                <div style={{ fontFamily: "var(--font-ui)", fontWeight: 700, fontSize: 15, color: "var(--ink-900)" }}>{t.title}</div>
                <div style={{ fontFamily: "var(--font-ui)", fontSize: 12.5, color: "var(--ink-500)", marginTop: 4 }}>{t.count} articles →</div>
              </div>
            </a>
          ))}
        </div>
      </section>

      {/* FAQ */}
      <section style={{ paddingTop: 72 }}>
        <SectionHeader kicker="Quick answers" title="Frequently asked"/>
        <div style={{ display: "flex", flexDirection: "column", gap: 0, background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 16, overflow: "hidden" }}>
          {filtered.map((f, i) => (
            <div key={i} style={{ borderBottom: i < filtered.length - 1 ? "1px solid var(--ink-200)" : "none" }}>
              <button onClick={()=>setOpen(open === i ? -1 : i)}
                style={{ width: "100%", display: "flex", justifyContent: "space-between", alignItems: "center", padding: "20px 24px", background: "transparent", border: "none", cursor: "pointer", textAlign: "left", gap: 16 }}>
                <span style={{ fontFamily: "var(--font-ui)", fontSize: 15.5, fontWeight: 600, color: "var(--ink-900)" }}>{f.q}</span>
                <span style={{ width: 30, height: 30, borderRadius: 999, background: open === i ? "var(--coral)" : "var(--cream-200)", color: open === i ? "#fff" : "var(--ink-700)", display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0, transition: "all .2s", transform: open === i ? "rotate(45deg)" : "rotate(0)" }}>
                  <Icon name="plus" size={14}/>
                </span>
              </button>
              {open === i && (
                <div style={{ padding: "0 24px 22px", fontFamily: "var(--font-ui)", fontSize: 14.5, color: "var(--ink-700)", lineHeight: 1.65, maxWidth: 760 }}>
                  {f.a}
                </div>
              )}
            </div>
          ))}
        </div>
      </section>

      {/* Contact band */}
      <section style={{ marginTop: 80 }}>
        <div style={{ padding: "44px 40px", background: "var(--cream-200)", borderRadius: 20, display: "grid", gridTemplateColumns: "1.4fr 1fr", gap: 24, alignItems: "center" }}>
          <div>
            <h3 style={{ margin: 0, fontFamily: "'Instrument Serif', serif", fontSize: 32, color: "var(--ink-900)", fontWeight: 400 }}>Still need help?</h3>
            <p style={{ margin: "8px 0 0", fontFamily: "var(--font-ui)", fontSize: 15, color: "var(--ink-700)" }}>Our team replies within a few hours, every day from 8am to 10pm Qatar time.</p>
          </div>
          <div style={{ display: "flex", gap: 10, justifyContent: "flex-end", flexWrap: "wrap" }}>
            <Btn kind="ghost" icon="chat">Live chat</Btn>
            <Btn kind="primary" icon="send">Email us</Btn>
          </div>
        </div>
      </section>
    </div>
  );
};

window.Help = Help;
