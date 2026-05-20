// Bazzar — Homepage with 3 layout variations

const HomeA = ({ listings, categories, cities, savedIds, onSave }) => (
  <>
    {/* HERO — Classic split with awning illustration */}
    <section style={{ background: "var(--cream-100)", borderBottom: "1px solid var(--ink-200)" }}>
      <div className="container" style={{ padding: "64px 24px 80px", display: "grid", gridTemplateColumns: "1.15fr 1fr", gap: 56, alignItems: "center" }}>
        <div>
          <div style={{ fontFamily: "var(--font-ui)", fontSize: 13, fontWeight: 700, color: "var(--coral)", letterSpacing: "0.14em", textTransform: "uppercase" }}>The Qatar marketplace</div>
          <h1 style={{ margin: "16px 0 0", fontFamily: "'Instrument Serif', serif", fontSize: "clamp(44px, 6vw, 76px)", lineHeight: 1.02, color: "var(--ink-900)", letterSpacing: "-0.02em", fontWeight: 400 }}>
            Buy, sell, and discover<br/>
            <em style={{ color: "var(--terracotta)" }}>right next door.</em>
          </h1>
          <p style={{ marginTop: 18, fontFamily: "var(--font-ui)", fontSize: 17, color: "var(--ink-700)", lineHeight: 1.55, maxWidth: 520 }}>
            From a used car in West Bay to a kitten in Al Khor — Bazzar is the friendly community marketplace where Qatar trades fairly, face-to-face.
          </p>
          {/* Big search */}
          <div style={{ marginTop: 30, background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 14, padding: 6, display: "flex", alignItems: "center", gap: 4, boxShadow: "0 10px 32px rgba(42,38,34,0.06)", maxWidth: 580 }}>
            <div style={{ flex: 1, display: "flex", alignItems: "center", paddingLeft: 14, gap: 10 }}>
              <Icon name="search" size={18}/>
              <input placeholder="What are you looking for?" style={{ flex: 1, border: "none", outline: "none", padding: "12px 0", fontSize: 15, fontFamily: "var(--font-ui)" }}/>
            </div>
            <div style={{ display: "flex", alignItems: "center", borderLeft: "1px solid var(--ink-200)", paddingLeft: 14, paddingRight: 6, color: "var(--ink-700)", fontFamily: "var(--font-ui)", fontSize: 14, gap: 6 }}>
              <Icon name="location" size={14}/>Doha
              <Icon name="chevronDown" size={14}/>
            </div>
            <Btn kind="primary" size="md" href="#/search">Search</Btn>
          </div>
          <div style={{ marginTop: 18, display: "flex", gap: 8, flexWrap: "wrap" }}>
            {["iPhone 15", "Land Cruiser", "2BR Lusail", "Maine Coon", "MacBook"].map(t => (
              <a key={t} href="#/search" style={{ padding: "6px 12px", border: "1px solid var(--ink-200)", borderRadius: 999, fontSize: 12.5, color: "var(--ink-700)", fontFamily: "var(--font-ui)", textDecoration: "none", background: "var(--cream-50)" }}>{t}</a>
            ))}
          </div>
          {/* Trust strip */}
          <div style={{ marginTop: 38, display: "flex", gap: 28, flexWrap: "wrap", color: "var(--ink-700)", fontFamily: "var(--font-ui)", fontSize: 13.5 }}>
            <span style={{ display: "inline-flex", alignItems: "center", gap: 8 }}><Icon name="shield" size={16}/> Verified sellers</span>
            <span style={{ display: "inline-flex", alignItems: "center", gap: 8 }}><Icon name="check" size={16}/> Free to post</span>
            <span style={{ display: "inline-flex", alignItems: "center", gap: 8 }}><Icon name="star" size={16}/> 4.8 community rating</span>
          </div>
        </div>
        {/* Hero illustration — giant awning + floating cards */}
        <div style={{ position: "relative", height: 460, display: "flex", alignItems: "center", justifyContent: "center" }}>
          <div style={{ width: "100%", maxWidth: 460, position: "relative" }}>
            <Logo size={220} withWordmark={false}/>
            {/* floating cards */}
            <div style={{ position: "absolute", top: 30, right: -10, width: 180, background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 12, padding: 10, boxShadow: "0 14px 40px rgba(42,38,34,0.10)", transform: "rotate(4deg)" }}>
              <SwatchImg swatch="warm" aspect="4 / 3" idx={0} photo="https://images.unsplash.com/photo-1583121274602-3e2820c69888?w=400&q=70&auto=format&fit=crop"/>
              <div style={{ marginTop: 8, fontSize: 12, fontFamily: "var(--font-ui)", color: "var(--ink-700)" }}>2022 Land Cruiser</div>
              <div style={{ fontFamily: "'Instrument Serif', serif", color: "var(--terracotta)", fontSize: 16 }}>245,000 QAR</div>
            </div>
            <div style={{ position: "absolute", bottom: 20, left: -20, width: 170, background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 12, padding: 10, boxShadow: "0 14px 40px rgba(42,38,34,0.10)", transform: "rotate(-5deg)" }}>
              <SwatchImg swatch="cool" aspect="4 / 3" idx={2} photo="https://images.unsplash.com/photo-1696446702183-be2e35b3a5e6?w=400&q=70&auto=format&fit=crop"/>
              <div style={{ marginTop: 8, fontSize: 12, fontFamily: "var(--font-ui)", color: "var(--ink-700)" }}>iPhone 15 Pro Max</div>
              <div style={{ fontFamily: "'Instrument Serif', serif", color: "var(--terracotta)", fontSize: 16 }}>4,200 QAR</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    {/* CATEGORIES */}
    <section className="container" style={{ padding: "72px 24px 24px" }}>
      <SectionHeader kicker="Browse" title="Every market in Qatar" action="See all categories" actionHref="#/search"/>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(160px, 1fr))", gap: 14 }}>
        {categories.map(c => (
          <a key={c.id} href={`#/search?cat=${c.id}`} className="cat-tile" style={{
            display: "flex", flexDirection: "column", gap: 12, padding: 18,
            background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 14,
            textDecoration: "none", color: "inherit", transition: "all .15s",
          }}
          onMouseEnter={(e)=>{ e.currentTarget.style.borderColor = "var(--coral)"; e.currentTarget.style.background = "var(--cream-50)"; }}
          onMouseLeave={(e)=>{ e.currentTarget.style.borderColor = "var(--ink-200)"; e.currentTarget.style.background = "#fff"; }}>
            <div style={{ width: 40, height: 40, borderRadius: 10, background: "var(--cream-200)", color: "var(--terracotta)", display: "flex", alignItems: "center", justifyContent: "center" }}>
              <Icon name={c.icon} size={22}/>
            </div>
            <div>
              <div style={{ fontFamily: "var(--font-ui)", fontWeight: 600, fontSize: 14.5, color: "var(--ink-900)" }}>{c.label}</div>
              <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, color: "var(--ink-500)", marginTop: 3 }}>{c.count} ads</div>
            </div>
          </a>
        ))}
      </div>
    </section>

    {/* FEATURED */}
    <section className="container" style={{ padding: "56px 24px 0" }}>
      <SectionHeader kicker="Curated" title="Featured today" action="See all featured" actionHref="#/search"/>
      <div className="grid-4">
        {listings.filter(l => l.featured).slice(0, 4).map(l => (
          <ListingCard key={l.id} listing={l} onSave={onSave} saved={savedIds.includes(l.id)}/>
        ))}
      </div>
    </section>

    {/* RECENT */}
    <section className="container" style={{ padding: "72px 24px 0" }}>
      <SectionHeader kicker="Just listed" title="Fresh from the neighborhood" action="See all" actionHref="#/search"/>
      <div className="grid-4">
        {listings.slice(0, 8).map(l => (
          <ListingCard key={l.id} listing={l} onSave={onSave} saved={savedIds.includes(l.id)}/>
        ))}
      </div>
    </section>

    {/* CTA BAND */}
    <section className="container" style={{ padding: "96px 24px 0" }}>
      <div style={{
        background: "var(--terracotta)", color: "#FFF7EE", borderRadius: 20, padding: "56px 48px",
        display: "grid", gridTemplateColumns: "1.4fr 1fr", gap: 32, alignItems: "center",
        position: "relative", overflow: "hidden",
      }}>
        {/* awning pattern bg */}
        <svg width="500" height="500" style={{ position: "absolute", right: -120, top: -120, opacity: 0.12 }} viewBox="0 0 80 80">
          <Logo size={500} withWordmark={false}/>
        </svg>
        <div>
          <h3 style={{ margin: 0, fontFamily: "'Instrument Serif', serif", fontSize: "clamp(32px, 4vw, 48px)", fontWeight: 400, lineHeight: 1.05, letterSpacing: "-0.015em" }}>
            Got something to sell? <em>List it in a minute.</em>
          </h3>
          <p style={{ marginTop: 14, opacity: 0.85, fontFamily: "var(--font-ui)", fontSize: 15.5, lineHeight: 1.55, maxWidth: 520 }}>
            Snap a few photos, write a short description, and your ad goes live to thousands of nearby buyers. Posting is free — always.
          </p>
        </div>
        <div style={{ display: "flex", justifyContent: "flex-end" }}>
          <Btn kind="ghost" size="lg" iconRight="arrow" href="#/post" style={{ background: "#fff", color: "var(--terracotta)", borderColor: "#fff" }}>Post your first ad</Btn>
        </div>
      </div>
    </section>

    {/* CITIES */}
    <section className="container" style={{ padding: "72px 24px 0" }}>
      <SectionHeader kicker="Local" title="Trade in your city"/>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(180px, 1fr))", gap: 10 }}>
        {cities.map((c, i) => (
          <a key={c} href={`#/search?city=${c}`} style={{ display: "flex", alignItems: "center", gap: 10, padding: "14px 16px", background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 12, textDecoration: "none", color: "var(--ink-900)", fontFamily: "var(--font-ui)", fontSize: 14, fontWeight: 500 }}>
            <Icon name="location" size={16}/>{c}
            <span style={{ marginLeft: "auto", fontSize: 12, color: "var(--ink-500)" }}>{(900 + i * 137).toLocaleString()}</span>
          </a>
        ))}
      </div>
    </section>
  </>
);

// VARIATION B — Editorial magazine layout, big serif, asymmetric
const HomeB = ({ listings, categories, savedIds, onSave }) => (
  <>
    {/* Hero — full-bleed editorial */}
    <section style={{ background: "var(--cream-50)", borderBottom: "1px solid var(--ink-200)" }}>
      <div className="container" style={{ padding: "88px 24px 64px" }}>
        <div style={{ display: "grid", gridTemplateColumns: "auto 1fr auto", gap: 32, alignItems: "end", marginBottom: 32 }}>
          <div style={{ fontFamily: "var(--font-ui)", fontSize: 13, color: "var(--ink-500)", letterSpacing: "0.18em", textTransform: "uppercase" }}>Vol. 4 · Spring '26</div>
          <div style={{ height: 1, background: "var(--ink-200)" }}/>
          <div style={{ fontFamily: "var(--font-ui)", fontSize: 13, color: "var(--ink-500)" }}>Doha — Lusail — Al Wakrah</div>
        </div>
        <h1 style={{ margin: 0, fontFamily: "'Instrument Serif', serif", fontSize: "clamp(60px, 9vw, 144px)", lineHeight: 0.92, color: "var(--ink-900)", letterSpacing: "-0.035em", fontWeight: 400 }}>
          The market<br/><em style={{ color: "var(--terracotta)" }}>has moved</em><br/>online.
        </h1>
        <div style={{ marginTop: 40, display: "grid", gridTemplateColumns: "1.4fr 1fr", gap: 56, alignItems: "end" }}>
          <p style={{ margin: 0, fontFamily: "var(--font-ui)", fontSize: 18, color: "var(--ink-700)", lineHeight: 1.6, maxWidth: 580 }}>
            Bazzar is a community classifieds platform built for Qatar — where neighbours sell to neighbours, prices are fair, and every deal is face-to-face. No middlemen, no shipping fees, no fuss.
          </p>
          <div style={{ display: "flex", gap: 10, justifyContent: "flex-end" }}>
            <Btn kind="ghost" size="lg" href="#/search">Browse listings</Btn>
            <Btn kind="primary" size="lg" iconRight="arrow" href="#/post">Sell something</Btn>
          </div>
        </div>
      </div>
    </section>

    {/* Featured magazine spread */}
    <section className="container" style={{ padding: "80px 24px 0" }}>
      <div style={{ display: "flex", alignItems: "baseline", justifyContent: "space-between", borderBottom: "1px solid var(--ink-300)", paddingBottom: 14, marginBottom: 32 }}>
        <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, fontWeight: 700, letterSpacing: "0.14em", textTransform: "uppercase", color: "var(--ink-900)" }}>01 — Featured this week</div>
        <a href="#/search" style={{ fontFamily: "var(--font-ui)", fontSize: 13, color: "var(--coral)", textDecoration: "none" }}>See all featured ↗</a>
      </div>
      <div style={{ display: "grid", gridTemplateColumns: "1.4fr 1fr 1fr", gap: 24 }}>
        {/* big feature */}
        {(() => {
          const f = listings.filter(l => l.featured)[0];
          return (
            <a href={`#/detail/${f.id}`} style={{ display: "flex", flexDirection: "column", textDecoration: "none", color: "inherit", gridRow: "span 2" }}>
              <SwatchImg swatch={f.swatch} aspect="4 / 5" idx={1} photo={f.photo}/>
              <div style={{ paddingTop: 20 }}>
                <Pill kind="featured">{f.subcategory}</Pill>
                <h3 style={{ margin: "12px 0 8px", fontFamily: "'Instrument Serif', serif", fontSize: 36, lineHeight: 1.05, color: "var(--ink-900)", fontWeight: 400 }}>{f.title}</h3>
                <p style={{ fontFamily: "var(--font-ui)", fontSize: 15, color: "var(--ink-700)", lineHeight: 1.55, margin: 0 }}>{f.desc?.slice(0, 180)}…</p>
                <div style={{ marginTop: 18, display: "flex", justifyContent: "space-between", alignItems: "center", paddingTop: 16, borderTop: "1px solid var(--ink-200)" }}>
                  <span style={{ fontFamily: "var(--font-ui)", fontSize: 13, color: "var(--ink-500)" }}>{f.location} · {f.postedAgo} ago</span>
                  <span style={{ fontFamily: "'Instrument Serif', serif", fontSize: 28, color: "var(--terracotta)" }}>{formatPrice(f.price)} <span style={{ fontSize: 14, fontFamily: "var(--font-ui)", color: "var(--ink-500)" }}>QAR</span></span>
                </div>
              </div>
            </a>
          );
        })()}
        {listings.filter(l => l.featured).slice(1, 5).map(l => (
          <ListingCard key={l.id} listing={l} onSave={onSave} saved={savedIds.includes(l.id)}/>
        ))}
      </div>
    </section>

    {/* Categories — horizontal pill scroll w/ giant numbers */}
    <section className="container" style={{ padding: "96px 24px 0" }}>
      <div style={{ display: "flex", alignItems: "baseline", justifyContent: "space-between", borderBottom: "1px solid var(--ink-300)", paddingBottom: 14, marginBottom: 32 }}>
        <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, fontWeight: 700, letterSpacing: "0.14em", textTransform: "uppercase", color: "var(--ink-900)" }}>02 — Marketplaces</div>
      </div>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 8 }}>
        {categories.slice(0, 8).map((c, i) => (
          <a key={c.id} href={`#/search?cat=${c.id}`} style={{ display: "flex", flexDirection: "column", padding: "20px 18px", border: "1px solid var(--ink-200)", background: "#fff", textDecoration: "none", color: "inherit", borderRadius: 4, transition: "background .15s", minHeight: 140, justifyContent: "space-between" }}
             onMouseEnter={(e)=>e.currentTarget.style.background="var(--cream-50)"}
             onMouseLeave={(e)=>e.currentTarget.style.background="#fff"}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
              <span style={{ fontFamily: "var(--font-ui)", fontSize: 11, color: "var(--ink-500)", letterSpacing: "0.1em" }}>0{i+1}</span>
              <Icon name={c.icon} size={20}/>
            </div>
            <div>
              <div style={{ fontFamily: "'Instrument Serif', serif", fontSize: 26, color: "var(--ink-900)", lineHeight: 1.05 }}>{c.label}</div>
              <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, color: "var(--ink-500)", marginTop: 4 }}>{c.count} listings</div>
            </div>
          </a>
        ))}
      </div>
    </section>

    {/* Latest */}
    <section className="container" style={{ padding: "96px 24px 0" }}>
      <div style={{ display: "flex", alignItems: "baseline", justifyContent: "space-between", borderBottom: "1px solid var(--ink-300)", paddingBottom: 14, marginBottom: 32 }}>
        <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, fontWeight: 700, letterSpacing: "0.14em", textTransform: "uppercase", color: "var(--ink-900)" }}>03 — Just posted</div>
        <a href="#/search" style={{ fontFamily: "var(--font-ui)", fontSize: 13, color: "var(--coral)", textDecoration: "none" }}>Browse all ↗</a>
      </div>
      <div className="grid-4">
        {listings.slice(0, 8).map(l => (
          <ListingCard key={l.id} listing={l} onSave={onSave} saved={savedIds.includes(l.id)}/>
        ))}
      </div>
    </section>
  </>
);

// VARIATION C — Dense utility, "get to the listings" layout
const HomeC = ({ listings, categories, cities, savedIds, onSave }) => {
  const [activeCat, setActiveCat] = useState(null);
  const filtered = activeCat ? listings.filter(l => l.category === activeCat) : listings;
  return (
    <>
      {/* Hero — compact, search-forward */}
      <section style={{ background: "linear-gradient(180deg, var(--cream-100) 0%, var(--cream-50) 100%)", borderBottom: "1px solid var(--ink-200)" }}>
        <div className="container" style={{ padding: "44px 24px 32px" }}>
          <div style={{ textAlign: "center", maxWidth: 720, margin: "0 auto" }}>
            <h1 style={{ margin: 0, fontFamily: "'Instrument Serif', serif", fontSize: "clamp(36px, 4.8vw, 56px)", lineHeight: 1.05, color: "var(--ink-900)", letterSpacing: "-0.02em", fontWeight: 400 }}>
              What are you looking for today?
            </h1>
            <p style={{ marginTop: 10, fontFamily: "var(--font-ui)", fontSize: 15, color: "var(--ink-700)" }}>
              112,378 active listings across Qatar — updated by the minute.
            </p>
            <div style={{ marginTop: 24, background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 14, padding: 5, display: "flex", alignItems: "center", gap: 4, boxShadow: "0 10px 30px rgba(42,38,34,0.06)" }}>
              <div style={{ flex: 1, display: "flex", alignItems: "center", paddingLeft: 14, gap: 10 }}>
                <Icon name="search" size={18}/>
                <input placeholder="Search 'iPhone', 'studio Lusail', 'kitten'…" style={{ flex: 1, border: "none", outline: "none", padding: "12px 0", fontSize: 15, fontFamily: "var(--font-ui)" }}/>
              </div>
              <div style={{ borderLeft: "1px solid var(--ink-200)", padding: "0 14px", fontFamily: "var(--font-ui)", fontSize: 14, color: "var(--ink-700)", display: "flex", alignItems: "center", gap: 6 }}>
                <Icon name="location" size={14}/>All Qatar<Icon name="chevronDown" size={14}/>
              </div>
              <Btn kind="primary" size="md" href="#/search">Search</Btn>
            </div>
            {/* category pill scroll */}
            <div style={{ marginTop: 24, display: "flex", gap: 8, justifyContent: "center", flexWrap: "wrap" }}>
              <Chip active={!activeCat} onClick={()=>setActiveCat(null)}>All</Chip>
              {categories.slice(0, 8).map(c => (
                <Chip key={c.id} active={activeCat === c.id} onClick={()=>setActiveCat(c.id)} icon={c.icon}>{c.label}</Chip>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Stats bar */}
      <section style={{ borderBottom: "1px solid var(--ink-200)", background: "#fff" }}>
        <div className="container" style={{ padding: "0 24px", display: "flex", justifyContent: "space-around", flexWrap: "wrap" }}>
          {[
            { n: "112k+", l: "Active listings" },
            { n: "42k", l: "Trusted sellers" },
            { n: "3.2k", l: "Deals daily" },
            { n: "4.8★", l: "Community rating" },
            { n: "60 sec", l: "To post an ad" },
          ].map((s, i) => (
            <div key={i} style={{ padding: "20px 16px", textAlign: "center", flex: 1, minWidth: 120 }}>
              <div style={{ fontFamily: "'Instrument Serif', serif", fontSize: 28, color: "var(--terracotta)" }}>{s.n}</div>
              <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, color: "var(--ink-500)", letterSpacing: "0.04em" }}>{s.l}</div>
            </div>
          ))}
        </div>
      </section>

      {/* Dense listings grid */}
      <section className="container" style={{ padding: "40px 24px 0", display: "grid", gridTemplateColumns: "240px 1fr", gap: 32 }}>
        {/* Sidebar */}
        <aside style={{ position: "sticky", top: 100, alignSelf: "flex-start" }} className="desktop-only">
          <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, fontWeight: 700, letterSpacing: "0.1em", color: "var(--ink-500)", textTransform: "uppercase", marginBottom: 12 }}>Categories</div>
          <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>
            {categories.map(c => (
              <a key={c.id} href={`#/search?cat=${c.id}`} style={{
                display: "flex", alignItems: "center", gap: 10, padding: "10px 12px",
                borderRadius: 8, textDecoration: "none", color: "var(--ink-900)",
                fontFamily: "var(--font-ui)", fontSize: 13.5, fontWeight: 500,
              }}
              onMouseEnter={(e)=>e.currentTarget.style.background="var(--cream-200)"}
              onMouseLeave={(e)=>e.currentTarget.style.background="transparent"}>
                <Icon name={c.icon} size={16}/>
                <span style={{ flex: 1 }}>{c.label}</span>
                <span style={{ fontSize: 11, color: "var(--ink-500)" }}>{c.count}</span>
              </a>
            ))}
          </div>
          <div style={{ marginTop: 24, padding: 16, background: "var(--cream-200)", borderRadius: 12 }}>
            <Logo size={28} withWordmark={false}/>
            <h4 style={{ margin: "10px 0 6px", fontFamily: "'Instrument Serif', serif", fontSize: 20, color: "var(--ink-900)" }}>Sell faster</h4>
            <p style={{ margin: 0, fontFamily: "var(--font-ui)", fontSize: 12.5, color: "var(--ink-700)", lineHeight: 1.55 }}>Feature your ad and reach 3× more buyers in your area.</p>
            <Btn kind="primary" size="sm" full style={{ marginTop: 12 }} href="#/post">Feature an ad</Btn>
          </div>
        </aside>

        <div>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
            <h2 style={{ margin: 0, fontFamily: "'Instrument Serif', serif", fontSize: 32, color: "var(--ink-900)", fontWeight: 400 }}>
              {activeCat ? categories.find(c=>c.id===activeCat)?.label : "Recent listings"}
            </h2>
            <div style={{ fontFamily: "var(--font-ui)", fontSize: 13, color: "var(--ink-500)" }}>{filtered.length} of 112,378</div>
          </div>
          {/* Featured strip */}
          <div style={{ marginBottom: 28, padding: "16px 18px", background: "var(--coral)", color: "#fff", borderRadius: 14, display: "flex", gap: 14, alignItems: "center" }}>
            <Icon name="star" size={18}/>
            <div style={{ flex: 1 }}>
              <div style={{ fontFamily: "var(--font-ui)", fontWeight: 700, fontSize: 14 }}>Featured spotlight</div>
              <div style={{ fontFamily: "var(--font-ui)", fontSize: 13, opacity: 0.9 }}>Get 3× the views — boost any listing from QAR 25.</div>
            </div>
            <Btn kind="ghost" size="sm" style={{ background: "#fff", color: "var(--coral)", borderColor: "#fff" }} href="#/post">Boost an ad</Btn>
          </div>
          <div className="grid-3">
            {filtered.slice(0, 9).map(l => (
              <ListingCard key={l.id} listing={l} onSave={onSave} saved={savedIds.includes(l.id)}/>
            ))}
          </div>
        </div>
      </section>
    </>
  );
};

const Home = ({ variant, ...props }) => {
  if (variant === "B") return <HomeB {...props}/>;
  if (variant === "C") return <HomeC {...props}/>;
  return <HomeA {...props}/>;
};

window.Home = Home;
