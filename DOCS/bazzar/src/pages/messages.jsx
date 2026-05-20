// Bazzar — Messages inbox
const Messages = ({ listings, threads }) => {
  const [activeId, setActiveId] = useState(threads[0]?.id);
  const active = threads.find(t => t.id === activeId);
  const listing = active && listings.find(l => l.id === active.listingId);
  const [draft, setDraft] = useState("");

  return (
    <div className="container" style={{ padding: "32px 24px 0" }}>
      <h1 style={{ margin: "0 0 24px", fontFamily: "'Instrument Serif', serif", fontSize: "clamp(36px, 4vw, 48px)", fontWeight: 400, color: "var(--ink-900)", letterSpacing: "-0.015em" }}>Messages</h1>
      <div style={{ display: "grid", gridTemplateColumns: "340px 1fr", gap: 0, background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 16, overflow: "hidden", minHeight: 640 }}>
        {/* THREADS LIST */}
        <aside style={{ borderRight: "1px solid var(--ink-200)", display: "flex", flexDirection: "column" }}>
          <div style={{ padding: "16px 16px 12px", borderBottom: "1px solid var(--ink-200)" }}>
            <div style={{ display: "flex", alignItems: "center", background: "var(--cream-100)", borderRadius: 10, padding: "8px 12px" }}>
              <Icon name="search" size={14}/>
              <input placeholder="Search messages…" style={{ flex: 1, border: "none", outline: "none", background: "transparent", padding: "0 10px", fontFamily: "var(--font-ui)", fontSize: 13.5 }}/>
            </div>
            <div style={{ display: "flex", gap: 6, marginTop: 12 }}>
              <Chip active>All</Chip>
              <Chip>Unread</Chip>
              <Chip>Selling</Chip>
              <Chip>Buying</Chip>
            </div>
          </div>
          <div style={{ overflowY: "auto", flex: 1 }}>
            {threads.map(t => {
              const tl = listings.find(l => l.id === t.listingId);
              const isActive = t.id === activeId;
              return (
                <button key={t.id} onClick={()=>setActiveId(t.id)} style={{
                  width: "100%", display: "flex", gap: 12, padding: 14,
                  background: isActive ? "var(--cream-200)" : "transparent",
                  border: "none", borderBottom: "1px solid var(--ink-200)",
                  textAlign: "left", cursor: "pointer", alignItems: "flex-start",
                }}>
                  <div style={{ position: "relative", flexShrink: 0 }}>
                    <div style={{ width: 44, height: 44, borderRadius: 999, background: "var(--cream-200)", display: "flex", alignItems: "center", justifyContent: "center", fontFamily: "'Instrument Serif', serif", color: "var(--terracotta)", fontSize: 20 }}>{t.partner.charAt(0)}</div>
                    {t.online && <div style={{ position: "absolute", bottom: 0, right: 0, width: 11, height: 11, borderRadius: 999, background: "#6B8E6B", border: "2px solid #fff" }}/>}
                  </div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: "flex", justifyContent: "space-between", gap: 8 }}>
                      <span style={{ fontFamily: "var(--font-ui)", fontWeight: 600, fontSize: 14, color: "var(--ink-900)" }}>{t.partner}</span>
                      <span style={{ fontFamily: "var(--font-ui)", fontSize: 11, color: "var(--ink-500)", flexShrink: 0 }}>{t.time}</span>
                    </div>
                    <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, color: "var(--ink-500)", marginTop: 2, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>re: {tl?.title}</div>
                    <div style={{ display: "flex", gap: 8, alignItems: "center", marginTop: 6 }}>
                      <div style={{ fontFamily: "var(--font-ui)", fontSize: 13, color: t.unread ? "var(--ink-900)" : "var(--ink-700)", fontWeight: t.unread ? 600 : 400, lineHeight: 1.4, display: "-webkit-box", WebkitLineClamp: 2, WebkitBoxOrient: "vertical", overflow: "hidden", flex: 1 }}>{t.lastMsg}</div>
                      {t.unread > 0 && <div style={{ background: "var(--coral)", color: "#fff", borderRadius: 999, padding: "2px 7px", fontSize: 11, fontFamily: "var(--font-ui)", fontWeight: 700 }}>{t.unread}</div>}
                    </div>
                  </div>
                </button>
              );
            })}
          </div>
        </aside>

        {/* CONVERSATION */}
        <div style={{ display: "flex", flexDirection: "column" }}>
          {active && (
            <>
              {/* Header */}
              <div style={{ padding: "14px 24px", borderBottom: "1px solid var(--ink-200)", display: "flex", alignItems: "center", gap: 14 }}>
                <div style={{ width: 42, height: 42, borderRadius: 999, background: "var(--cream-200)", display: "flex", alignItems: "center", justifyContent: "center", fontFamily: "'Instrument Serif', serif", color: "var(--terracotta)", fontSize: 18 }}>{active.partner.charAt(0)}</div>
                <div>
                  <div style={{ fontFamily: "var(--font-ui)", fontWeight: 700, fontSize: 15, color: "var(--ink-900)" }}>{active.partner}</div>
                  <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, color: "var(--ink-500)" }}>{active.online ? "Online now" : "Last seen yesterday"} · ★ 4.9</div>
                </div>
                <div style={{ marginLeft: "auto", display: "flex", gap: 6 }}>
                  <Btn kind="ghost" size="sm" icon="phone">Call</Btn>
                  <Btn kind="ghost" size="sm" icon="flag">Report</Btn>
                </div>
              </div>

              {/* Listing strip */}
              {listing && (
                <a href={`#/detail/${listing.id}`} style={{ display: "flex", gap: 14, padding: "12px 24px", background: "var(--cream-100)", borderBottom: "1px solid var(--ink-200)", textDecoration: "none", color: "inherit" }}>
                  <div style={{ width: 70, height: 56, borderRadius: 8, overflow: "hidden", flexShrink: 0 }}>
                    <SwatchImg swatch={listing.swatch} aspect="5 / 4" idx={Number(listing.id.slice(-1))} photo={listing.photo}/>
                  </div>
                  <div style={{ flex: 1 }}>
                    <div style={{ fontFamily: "var(--font-ui)", fontWeight: 600, fontSize: 13.5, color: "var(--ink-900)", display: "-webkit-box", WebkitLineClamp: 1, WebkitBoxOrient: "vertical", overflow: "hidden" }}>{listing.title}</div>
                    <div style={{ fontFamily: "'Instrument Serif', serif", color: "var(--terracotta)", fontSize: 17, marginTop: 2 }}>{formatPrice(listing.price)} <span style={{ fontFamily: "var(--font-ui)", fontSize: 11, color: "var(--ink-500)" }}>QAR</span></div>
                  </div>
                  <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, color: "var(--coral)", alignSelf: "center" }}>View listing →</div>
                </a>
              )}

              {/* Messages */}
              <div style={{ flex: 1, padding: 24, display: "flex", flexDirection: "column", gap: 16, overflowY: "auto", background: "var(--cream-50)" }}>
                {(active.messages || [
                  { from: "them", text: "Hi, is this still available?", time: "10:30" },
                  { from: "me", text: "Yes! Are you interested in viewing?", time: "10:32" },
                  { from: "them", text: active.lastMsg, time: active.time },
                ]).map((m, i) => (
                  <div key={i} style={{ display: "flex", justifyContent: m.from === "me" ? "flex-end" : "flex-start" }}>
                    <div style={{
                      maxWidth: "70%", padding: "10px 14px",
                      background: m.from === "me" ? "var(--coral)" : "#fff",
                      color: m.from === "me" ? "#fff" : "var(--ink-900)",
                      borderRadius: m.from === "me" ? "16px 16px 4px 16px" : "16px 16px 16px 4px",
                      fontFamily: "var(--font-ui)", fontSize: 14, lineHeight: 1.5,
                      border: m.from === "me" ? "none" : "1px solid var(--ink-200)",
                    }}>
                      {m.text}
                      <div style={{ fontSize: 10.5, opacity: 0.7, marginTop: 4 }}>{m.time}</div>
                    </div>
                  </div>
                ))}
              </div>

              {/* Quick replies */}
              <div style={{ padding: "8px 24px", display: "flex", gap: 6, flexWrap: "wrap", borderTop: "1px solid var(--ink-200)" }}>
                {["Is this still available?", "Can you do 200 QAR?", "When can I come see it?", "Where exactly?"].map(q => (
                  <Chip key={q} onClick={()=>setDraft(q)}>{q}</Chip>
                ))}
              </div>

              {/* Composer */}
              <div style={{ padding: 18, borderTop: "1px solid var(--ink-200)", display: "flex", gap: 10, alignItems: "center" }}>
                <button style={{ background: "var(--cream-200)", border: "none", width: 38, height: 38, borderRadius: 10, color: "var(--ink-700)", cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center" }}><Icon name="camera"/></button>
                <input value={draft} onChange={e=>setDraft(e.target.value)} placeholder="Type a message…"
                  style={{ flex: 1, padding: "11px 16px", border: "1px solid var(--ink-200)", borderRadius: 10, fontFamily: "var(--font-ui)", fontSize: 14, outline: "none" }}/>
                <Btn kind="primary" icon="send" onClick={()=>setDraft("")}>Send</Btn>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
};

window.Messages = Messages;
