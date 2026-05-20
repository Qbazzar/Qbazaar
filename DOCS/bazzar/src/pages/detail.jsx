// Bazzar — Ad detail page
const Detail = ({ listings, listingId, onSave, savedIds }) => {
  const listing = listings.find(l => l.id === listingId) || listings[0];
  const [activeImg, setActiveImg] = useState(0);
  const [showPhone, setShowPhone] = useState(false);
  const similar = listings.filter(l => l.category === listing.category && l.id !== listing.id).slice(0, 4);
  const isSaved = savedIds.includes(listing.id);

  return (
    <div className="container" style={{ padding: "24px 24px 0" }}>
      {/* Breadcrumbs */}
      <div style={{ display: "flex", alignItems: "center", gap: 8, fontFamily: "var(--font-ui)", fontSize: 12.5, color: "var(--ink-500)", marginBottom: 18, flexWrap: "wrap" }}>
        <a href="#/home" style={{ color: "var(--ink-500)", textDecoration: "none" }}>Home</a>
        <Icon name="chevron" size={12}/>
        <a href={`#/search?cat=${listing.category}`} style={{ color: "var(--ink-500)", textDecoration: "none" }}>{listing.category}</a>
        <Icon name="chevron" size={12}/>
        <a href={`#/search?cat=${listing.category}`} style={{ color: "var(--ink-500)", textDecoration: "none" }}>{listing.subcategory}</a>
        <Icon name="chevron" size={12}/>
        <span style={{ color: "var(--ink-900)" }}>{listing.title.slice(0, 40)}…</span>
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "1fr 380px", gap: 32 }}>
        {/* MAIN COLUMN */}
        <div>
          {/* Gallery */}
          <div style={{ borderRadius: 16, overflow: "hidden", background: "var(--cream-200)" }}>
            <SwatchImg swatch={listing.swatch} aspect="4 / 3" idx={activeImg} category={listing.category} photo={activeImg === 0 ? listing.photo : undefined}/>
          </div>
          <div style={{ display: "flex", gap: 8, marginTop: 12 }}>
            {Array.from({ length: listing.thumbs }, (_, i) => (
              <button key={i} onClick={()=>setActiveImg(i)}
                style={{ width: 80, height: 60, borderRadius: 8, overflow: "hidden", border: `2px solid ${activeImg===i?"var(--coral)":"transparent"}`, padding: 0, cursor: "pointer" }}>
                <SwatchImg swatch={listing.swatch} aspect="4 / 3" idx={i} category={listing.category} photo={i === 0 ? listing.photo : undefined}/>
              </button>
            ))}
          </div>

          {/* Title & meta */}
          <div style={{ marginTop: 32 }}>
            {listing.featured && <Pill kind="featured">Featured ad</Pill>}
            <h1 style={{ margin: "10px 0 12px", fontFamily: "'Instrument Serif', serif", fontSize: "clamp(32px, 4vw, 44px)", fontWeight: 400, color: "var(--ink-900)", lineHeight: 1.1, letterSpacing: "-0.015em" }}>
              {listing.title}
            </h1>
            <div style={{ display: "flex", alignItems: "baseline", gap: 14, flexWrap: "wrap" }}>
              <span style={{ fontFamily: "'Instrument Serif', serif", fontSize: 48, color: "var(--terracotta)", lineHeight: 1, fontWeight: 400 }}>
                {formatPrice(listing.price)} <span style={{ fontFamily: "var(--font-ui)", fontSize: 18, color: "var(--ink-700)" }}>{listing.currency}</span>
              </span>
            </div>
            <div style={{ display: "flex", gap: 18, marginTop: 14, fontFamily: "var(--font-ui)", fontSize: 13.5, color: "var(--ink-700)", flexWrap: "wrap" }}>
              <span style={{ display: "inline-flex", alignItems: "center", gap: 6 }}><Icon name="location" size={14}/>{listing.location}</span>
              <span>·</span>
              <span>Posted {listing.postedAgo} ago</span>
              <span>·</span>
              <span>Ad ID {listing.id}</span>
              <span>·</span>
              <span style={{ display: "inline-flex", alignItems: "center", gap: 6 }}><Icon name="eye" size={14}/>1,284 views</span>
            </div>
          </div>

          {/* Specs */}
          <div style={{ marginTop: 32, padding: "20px 24px", background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 14, display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(140px, 1fr))", gap: 18 }}>
            <Spec label="Category" value={listing.subcategory}/>
            <Spec label="Condition" value={listing.condition}/>
            <Spec label="Location" value={listing.location.split(",")[0]}/>
            <Spec label="Posted" value={listing.postedAgo + " ago"}/>
          </div>

          {/* Tags */}
          {listing.tags && (
            <div style={{ marginTop: 20, display: "flex", gap: 8, flexWrap: "wrap" }}>
              {listing.tags.map(t => <Pill key={t} kind="sage">{t}</Pill>)}
            </div>
          )}

          {/* Description */}
          <div style={{ marginTop: 32 }}>
            <h2 style={{ margin: "0 0 14px", fontFamily: "'Instrument Serif', serif", fontSize: 28, fontWeight: 400, color: "var(--ink-900)" }}>Description</h2>
            <p style={{ fontFamily: "var(--font-ui)", fontSize: 15.5, color: "var(--ink-700)", lineHeight: 1.7, margin: 0 }}>
              {listing.desc}
            </p>
            <p style={{ fontFamily: "var(--font-ui)", fontSize: 15.5, color: "var(--ink-700)", lineHeight: 1.7, marginTop: 14 }}>
              Available for viewing any evening this week and on weekends. Cash on collection or bank transfer preferred. Please contact only if you're seriously interested — no time-wasters please. Happy to answer any questions about condition or history.
            </p>
          </div>

          {/* Map placeholder */}
          <div style={{ marginTop: 32 }}>
            <h2 style={{ margin: "0 0 14px", fontFamily: "'Instrument Serif', serif", fontSize: 28, fontWeight: 400, color: "var(--ink-900)" }}>Location</h2>
            <div style={{ height: 260, borderRadius: 14, overflow: "hidden", position: "relative", background: "var(--cream-200)", border: "1px solid var(--ink-200)" }}>
              <svg viewBox="0 0 400 260" width="100%" height="100%">
                {/* abstract map */}
                <defs>
                  <pattern id="mapgrid" width="20" height="20" patternUnits="userSpaceOnUse">
                    <path d="M20 0H0V20" fill="none" stroke="#D8CFC0" strokeWidth="0.5"/>
                  </pattern>
                </defs>
                <rect width="400" height="260" fill="#F1EBE2"/>
                <rect width="400" height="260" fill="url(#mapgrid)"/>
                <path d="M0 130 Q100 100 200 140 T400 130" stroke="#C8BFB1" strokeWidth="14" fill="none"/>
                <path d="M120 0 L130 260" stroke="#C8BFB1" strokeWidth="10" fill="none"/>
                <path d="M0 90 Q200 60 400 100" stroke="#C8BFB1" strokeWidth="6" fill="none"/>
                <circle cx="200" cy="130" r="22" fill="var(--coral)" opacity="0.25"/>
                <circle cx="200" cy="130" r="10" fill="var(--coral)"/>
                <text x="220" y="135" fontFamily="DM Sans, sans-serif" fontSize="12" fill="#2A2622" fontWeight="600">{listing.location}</text>
              </svg>
            </div>
          </div>

          {/* Safety */}
          <div style={{ marginTop: 24, padding: 20, background: "var(--cream-200)", borderRadius: 14, display: "flex", gap: 14 }}>
            <div style={{ width: 38, height: 38, borderRadius: 999, background: "#fff", display: "flex", alignItems: "center", justifyContent: "center", color: "var(--terracotta)", flexShrink: 0 }}>
              <Icon name="shield" size={18}/>
            </div>
            <div>
              <div style={{ fontFamily: "var(--font-ui)", fontWeight: 700, fontSize: 14, color: "var(--ink-900)" }}>Stay safe when meeting</div>
              <div style={{ fontFamily: "var(--font-ui)", fontSize: 13, color: "var(--ink-700)", lineHeight: 1.55, marginTop: 4 }}>
                Meet in public, inspect goods before paying, never wire money to strangers. <a href="#/help" style={{ color: "var(--coral)" }}>Read our safety tips →</a>
              </div>
            </div>
          </div>
        </div>

        {/* SIDEBAR — Seller */}
        <aside>
          <div style={{ position: "sticky", top: 100 }}>
            <div style={{ padding: 24, background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 16 }}>
              <div style={{ display: "flex", gap: 12, alignItems: "center", marginBottom: 18 }}>
                <div style={{ width: 56, height: 56, borderRadius: 999, background: "var(--cream-200)", display: "flex", alignItems: "center", justifyContent: "center", fontFamily: "'Instrument Serif', serif", fontSize: 24, color: "var(--terracotta)" }}>
                  {listing.seller.charAt(0)}
                </div>
                <div>
                  <div style={{ fontFamily: "var(--font-ui)", fontWeight: 700, fontSize: 16, color: "var(--ink-900)" }}>{listing.seller}</div>
                  <div style={{ fontFamily: "var(--font-ui)", fontSize: 12.5, color: "var(--ink-500)" }}>Member since {listing.sellerSince} · 47 ads</div>
                </div>
              </div>
              <div style={{ display: "flex", gap: 6, alignItems: "center", marginBottom: 18, fontFamily: "var(--font-ui)", fontSize: 13 }}>
                <div style={{ display: "inline-flex", gap: 1 }}>
                  {[1,2,3,4,5].map(i => <Icon key={i} name="star" size={14} stroke={0}/>)}
                </div>
                <span style={{ color: "var(--ink-700)", fontWeight: 600 }}>4.9</span>
                <span style={{ color: "var(--ink-500)" }}>(38 reviews)</span>
              </div>
              <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                <Btn kind="primary" size="lg" full icon="chat" href="#/messages">Message seller</Btn>
                <Btn kind="ghost" size="lg" full icon="phone" onClick={()=>setShowPhone(true)}>
                  {showPhone ? "+974 5512 ▒▒▒▒  →  +974 5512 4488" : "Show phone number"}
                </Btn>
                <Btn kind="soft" size="lg" full icon={isSaved ? "heart" : "heart"} onClick={()=>onSave(listing.id)}>
                  {isSaved ? "Saved to favourites" : "Save ad"}
                </Btn>
              </div>
              <div style={{ marginTop: 20, paddingTop: 20, borderTop: "1px solid var(--ink-200)", display: "flex", justifyContent: "space-between", fontFamily: "var(--font-ui)", fontSize: 12.5, color: "var(--ink-500)" }}>
                <a href="#" style={{ color: "var(--ink-500)", textDecoration: "none", display: "inline-flex", alignItems: "center", gap: 4 }}><Icon name="flag" size={13}/>Report ad</a>
                <a href="#" style={{ color: "var(--ink-500)", textDecoration: "none", display: "inline-flex", alignItems: "center", gap: 4 }}><Icon name="send" size={13}/>Share</a>
              </div>
            </div>

            {/* Trust badges */}
            <div style={{ marginTop: 16, padding: "14px 18px", background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 14 }}>
              <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, fontWeight: 700, letterSpacing: "0.1em", color: "var(--ink-500)", textTransform: "uppercase", marginBottom: 10 }}>This seller</div>
              <div style={{ display: "flex", flexDirection: "column", gap: 8, fontFamily: "var(--font-ui)", fontSize: 13.5, color: "var(--ink-700)" }}>
                <div style={{ display: "flex", gap: 8, alignItems: "center" }}><Icon name="check" size={14}/> ID verified</div>
                <div style={{ display: "flex", gap: 8, alignItems: "center" }}><Icon name="check" size={14}/> Phone verified</div>
                <div style={{ display: "flex", gap: 8, alignItems: "center" }}><Icon name="check" size={14}/> Email verified</div>
                <div style={{ display: "flex", gap: 8, alignItems: "center" }}><Icon name="check" size={14}/> Active in last 24h</div>
              </div>
            </div>
          </div>
        </aside>
      </div>

      {/* Similar */}
      <section style={{ padding: "72px 0 0" }}>
        <SectionHeader kicker="More like this" title="Similar listings nearby" action="See all" actionHref="#/search"/>
        <div className="grid-4">
          {similar.map(l => <ListingCard key={l.id} listing={l} onSave={onSave} saved={savedIds.includes(l.id)}/>)}
        </div>
      </section>
    </div>
  );
};

const Spec = ({ label, value }) => (
  <div>
    <div style={{ fontFamily: "var(--font-ui)", fontSize: 11, fontWeight: 700, letterSpacing: "0.1em", color: "var(--ink-500)", textTransform: "uppercase", marginBottom: 4 }}>{label}</div>
    <div style={{ fontFamily: "var(--font-ui)", fontSize: 14.5, color: "var(--ink-900)", fontWeight: 600 }}>{value}</div>
  </div>
);

window.Detail = Detail;
