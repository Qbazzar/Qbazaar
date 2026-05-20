// Bazzar — Saved ads
const Saved = ({ listings, savedIds, onSave }) => {
  // Default to a few saved if none yet
  const defaultSaved = savedIds.length ? savedIds : ["L-2402", "L-2403", "L-2410", "L-2407"];
  const saved = listings.filter(l => defaultSaved.includes(l.id));
  return (
    <div className="container" style={{ padding: "32px 24px 0" }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-end", marginBottom: 8 }}>
        <h1 style={{ margin: 0, fontFamily: "'Instrument Serif', serif", fontSize: "clamp(36px, 4vw, 48px)", fontWeight: 400, color: "var(--ink-900)", letterSpacing: "-0.015em" }}>Saved ads</h1>
        <div style={{ fontFamily: "var(--font-ui)", fontSize: 14, color: "var(--ink-500)" }}>{saved.length} items</div>
      </div>
      <p style={{ margin: "8px 0 28px", fontFamily: "var(--font-ui)", fontSize: 15, color: "var(--ink-700)" }}>Listings you've bookmarked. We'll notify you if the price drops or the seller responds to a question.</p>

      <div style={{ display: "flex", gap: 8, marginBottom: 28, flexWrap: "wrap" }}>
        <Chip active>All saved ({saved.length})</Chip>
        <Chip>Price drops (2)</Chip>
        <Chip>Recently posted</Chip>
        <Chip>Available</Chip>
        <button style={{ marginLeft: "auto", background: "none", border: "none", color: "var(--ink-500)", fontFamily: "var(--font-ui)", fontSize: 13, cursor: "pointer", display: "inline-flex", gap: 6, alignItems: "center" }}>Clear all <Icon name="close" size={12}/></button>
      </div>

      {saved.length === 0 ? (
        <EmptyState
          title="No saved ads yet"
          sub="Tap the heart on any ad to save it for later. We'll keep your finds in one place."
          cta="Browse listings"
          href="#/search"
        />
      ) : (
        <div className="grid-4">
          {saved.map(l => <ListingCard key={l.id} listing={l} onSave={onSave} saved/>)}
        </div>
      )}

      {/* Suggested */}
      <section style={{ marginTop: 72 }}>
        <SectionHeader kicker="You might also like" title="Based on what you've saved" action="See more" actionHref="#/search"/>
        <div className="grid-4">
          {listings.slice(4, 8).map(l => <ListingCard key={l.id} listing={l} onSave={onSave} saved={defaultSaved.includes(l.id)}/>)}
        </div>
      </section>
    </div>
  );
};

const EmptyState = ({ title, sub, cta, href }) => (
  <div style={{ padding: "80px 32px", textAlign: "center", background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 16 }}>
    <div style={{ display: "inline-flex" }}><Logo size={72} withWordmark={false}/></div>
    <h3 style={{ margin: "20px 0 6px", fontFamily: "'Instrument Serif', serif", fontSize: 28, fontWeight: 400, color: "var(--ink-900)" }}>{title}</h3>
    <p style={{ margin: 0, fontFamily: "var(--font-ui)", fontSize: 14.5, color: "var(--ink-700)", maxWidth: 380, marginLeft: "auto", marginRight: "auto" }}>{sub}</p>
    {cta && <div style={{ marginTop: 22 }}><Btn kind="primary" href={href}>{cta}</Btn></div>}
  </div>
);

window.Saved = Saved;
window.EmptyState = EmptyState;
