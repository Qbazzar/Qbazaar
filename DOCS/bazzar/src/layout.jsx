// Bazzar — shared layout pieces: Logo, Header, Footer, Sidebar, primitives
const { useState, useEffect, useMemo, useRef } = React;

// ---------- ICONS (lightweight inline SVG, stroke-based) ----------
const Icon = ({ name, size = 20, stroke = 1.6 }) => {
  const p = {
    car:       <><path d="M3 13l2-5a3 3 0 0 1 3-2h8a3 3 0 0 1 3 2l2 5"/><rect x="2" y="13" width="20" height="6" rx="2"/><circle cx="7" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/></>,
    home:      <><path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/></>,
    device:    <><rect x="4" y="3" width="16" height="14" rx="2"/><path d="M2 21h20"/></>,
    sofa:      <><path d="M3 12v5a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/><path d="M5 12V8a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v4"/><path d="M3 15h18"/></>,
    shirt:     <><path d="M6 4l3-1 3 2 3-2 3 1 3 3-3 3v10H6V10L3 7z"/></>,
    briefcase: <><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></>,
    wrench:    <><path d="M14 6a4 4 0 0 0 5 5l-9 9-5-5 9-9z"/><circle cx="6" cy="18" r="1.5"/></>,
    paw:       <><circle cx="6" cy="10" r="2"/><circle cx="10" cy="6" r="2"/><circle cx="14" cy="6" r="2"/><circle cx="18" cy="10" r="2"/><path d="M7 18a5 5 0 0 1 10 0c0 2-2 3-5 3s-5-1-5-3z"/></>,
    ball:      <><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3v18M5 6c4 3 10 3 14 0M5 18c4-3 10-3 14 0"/></>,
    baby:      <><circle cx="12" cy="9" r="4"/><path d="M5 21c0-4 3-7 7-7s7 3 7 7"/><circle cx="10" cy="9" r=".7" fill="currentColor"/><circle cx="14" cy="9" r=".7" fill="currentColor"/></>,
    leaf:      <><path d="M4 20c0-9 7-16 16-16 0 9-7 16-16 16z"/><path d="M4 20l9-9"/></>,
    package:   <><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/></>,
    search:    <><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></>,
    heart:     <><path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z"/></>,
    bell:      <><path d="M6 16V10a6 6 0 1 1 12 0v6l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/></>,
    user:      <><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></>,
    plus:      <><path d="M12 5v14M5 12h14"/></>,
    chat:      <><path d="M21 12a8 8 0 0 1-12 7l-5 1 1-5A8 8 0 1 1 21 12z"/></>,
    location:  <><path d="M12 22s7-7 7-13a7 7 0 1 0-14 0c0 6 7 13 7 13z"/><circle cx="12" cy="9" r="2.5"/></>,
    chevron:   <><path d="m9 6 6 6-6 6"/></>,
    chevronDown:<><path d="m6 9 6 6 6-6"/></>,
    back:      <><path d="M15 18l-6-6 6-6"/></>,
    menu:      <><path d="M3 6h18M3 12h18M3 18h18"/></>,
    close:     <><path d="M6 6l12 12M18 6L6 18"/></>,
    grid:      <><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></>,
    list:      <><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1" fill="currentColor"/><circle cx="4" cy="12" r="1" fill="currentColor"/><circle cx="4" cy="18" r="1" fill="currentColor"/></>,
    shield:    <><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/></>,
    star:      <><path d="m12 3 2.6 5.5 6 .9-4.3 4.2 1 6L12 16.8 6.7 19.6l1-6L3.4 9.4l6-.9z"/></>,
    check:     <><path d="M5 12l5 5 9-12"/></>,
    eye:       <><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></>,
    camera:    <><path d="M4 7h3l2-3h6l2 3h3a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="4"/></>,
    phone:     <><path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 6a2 2 0 0 1 2-2z"/></>,
    flag:      <><path d="M5 21V4M5 4h12l-2 4 2 4H5"/></>,
    send:      <><path d="M22 2 11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></>,
    arrow:     <><path d="M5 12h14M13 6l6 6-6 6"/></>,
    cart:      <><path d="M3 4h2l2.5 12h11l2-9H6"/><circle cx="10" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/></>,
    facebook:  <><path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v8h4v-8h3l1-4h-4V8a0 0 0 0 1 0 0z"/></>,
    instagram: <><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.8" fill="currentColor"/></>,
    x:         <><path d="M4 4l16 16M20 4 4 20"/></>,
    tiktok:    <><path d="M14 4v10a4 4 0 1 1-4-4"/><path d="M14 4c0 3 2 5 5 5"/></>,
  }[name] || <circle cx="12" cy="12" r="9"/>;
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor"
      strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round" aria-hidden>
      {p}
    </svg>
  );
};

// ---------- LOGO ----------
const Logo = ({ size = 32, withWordmark = true, accent = "#EE8765", deep = "#B85A45", ink = "#2A2622", stripe = "#FFFFFF" }) => {
  // Compact awning logomark
  const w = size, h = size;
  return (
    <a href="#/home" style={{ display: "inline-flex", alignItems: "center", gap: 10, textDecoration: "none", color: "inherit" }}>
      <svg width={w} height={h} viewBox="0 0 80 80" aria-label="Bazzar">
        {/* Awning panels */}
        <path d="M8 28 L24 6 L24 44 Z" fill={accent}/>
        <path d="M24 6 L24 44 L40 6 Z" fill={stripe} stroke="#EEE6DA" strokeWidth="0.5"/>
        <path d="M40 6 L40 44 L56 6 Z" fill={accent}/>
        <path d="M56 6 L56 44 L72 28 Z" fill={stripe} stroke="#EEE6DA" strokeWidth="0.5"/>
        {/* Valance scallops */}
        <path d="M8 44 L24 44 L20 60 Q16 68 12 60 Z" fill={deep}/>
        <path d="M24 44 L40 44 L36 60 Q32 68 28 60 Z" fill="#E8E2D8"/>
        <path d="M40 44 L56 44 L52 60 Q48 68 44 60 Z" fill={deep}/>
        <path d="M56 44 L72 44 L68 60 Q64 68 60 60 Z" fill="#E8E2D8"/>
      </svg>
      {withWordmark && (
        <span style={{ fontFamily: "'Instrument Serif', serif", fontSize: size * 0.9, fontStyle: "italic", color: ink, letterSpacing: "-0.01em", lineHeight: 1 }}>
          Bazzar
        </span>
      )}
    </a>
  );
};

// ---------- ABSTRACT PHOTO PLACEHOLDER ----------
// Uses subtle stripes + an icon hint based on category. If a `photo` URL is
// provided we render that as <img>, falling back to the swatch on load error.
const SwatchImg = ({ category = "general", swatch = "warm", aspect = "4 / 3", label, idx = 0, photo }) => {
  const [failed, setFailed] = useState(false);
  const showPhoto = photo && !failed;
  const palettes = {
    warm:    ["#F3D9C8", "#EE8765", "#B85A45"],
    cool:    ["#D9E2E8", "#9BB1BD", "#5F7785"],
    earth:   ["#E6DBC9", "#C4A57E", "#7A5C3E"],
    neutral: ["#E8E2D8", "#C8BFB1", "#8A8175"],
  };
  const [c1, c2, c3] = palettes[swatch] || palettes.warm;
  const variants = [
    // 0: diagonal stripes + circle
    <g key="v0">
      <rect width="200" height="150" fill={c1}/>
      <g opacity="0.55">
        {Array.from({ length: 14 }, (_, i) => (
          <line key={i} x1={-50 + i * 20} y1="200" x2={50 + i * 20} y2="-50" stroke={c2} strokeWidth="6"/>
        ))}
      </g>
      <circle cx="150" cy="40" r="22" fill={c3} opacity="0.85"/>
    </g>,
    // 1: horizontal bands
    <g key="v1">
      <rect width="200" height="150" fill={c1}/>
      <rect y="0" width="200" height="40" fill={c2} opacity="0.7"/>
      <rect y="100" width="200" height="50" fill={c3} opacity="0.75"/>
      <rect x="20" y="55" width="160" height="40" fill="none" stroke={c3} strokeWidth="2" strokeDasharray="4 4"/>
    </g>,
    // 2: arch
    <g key="v2">
      <rect width="200" height="150" fill={c1}/>
      <path d="M30 150 L30 90 Q100 30 170 90 L170 150 Z" fill={c2} opacity="0.85"/>
      <circle cx="100" cy="100" r="12" fill={c3}/>
    </g>,
    // 3: grid blocks
    <g key="v3">
      <rect width="200" height="150" fill={c1}/>
      <rect x="20" y="20" width="60" height="40" fill={c2} opacity="0.8"/>
      <rect x="100" y="20" width="80" height="60" fill={c3} opacity="0.7"/>
      <rect x="20" y="80" width="100" height="50" fill={c2} opacity="0.6"/>
      <rect x="140" y="100" width="40" height="30" fill={c3} opacity="0.8"/>
    </g>,
  ];
  const variant = variants[idx % variants.length];
  return (
    <div style={{ aspectRatio: aspect, width: "100%", borderRadius: 10, overflow: "hidden", position: "relative", background: c1 }}>
      {showPhoto ? (
        <img src={photo} alt={label || ""} onError={() => setFailed(true)} loading="lazy"
             style={{ width: "100%", height: "100%", objectFit: "cover", display: "block" }}/>
      ) : (
        <svg viewBox="0 0 200 150" width="100%" height="100%" preserveAspectRatio="xMidYMid slice">
          {variant}
        </svg>
      )}
      {label && (
        <span style={{
          position: "absolute", left: 8, bottom: 8, padding: "3px 7px", fontSize: 10,
          fontFamily: "ui-monospace, SFMono-Regular, Menlo, monospace", letterSpacing: "0.04em",
          color: "#2A2622", background: "rgba(255,255,255,.78)", borderRadius: 4, textTransform: "uppercase"
        }}>{label}</span>
      )}
    </div>
  );
};

// ---------- PRIMITIVE BUTTONS / INPUTS ----------
const Btn = ({ kind = "primary", size = "md", icon, iconRight, children, onClick, style, type, full, href }) => {
  const sizes = {
    sm: { padding: "8px 14px", fontSize: 13, gap: 6, height: 34, radius: 8 },
    md: { padding: "11px 18px", fontSize: 14, gap: 8, height: 42, radius: 10 },
    lg: { padding: "14px 24px", fontSize: 15, gap: 10, height: 50, radius: 12 },
  }[size];
  const kinds = {
    primary: { background: "var(--coral)", color: "#fff", border: "1px solid var(--coral)" },
    deep:    { background: "var(--terracotta)", color: "#fff", border: "1px solid var(--terracotta)" },
    ghost:   { background: "transparent", color: "var(--ink-900)", border: "1px solid var(--ink-200)" },
    soft:    { background: "var(--cream-200)", color: "var(--ink-900)", border: "1px solid transparent" },
    text:    { background: "transparent", color: "var(--coral)", border: "1px solid transparent", padding: "8px 4px" },
  }[kind];
  const Cmp = href ? "a" : "button";
  return (
    <Cmp href={href} type={type} onClick={onClick}
      style={{
        ...kinds, ...sizes,
        display: "inline-flex", alignItems: "center", justifyContent: "center",
        fontFamily: "var(--font-ui)", fontWeight: 600, cursor: "pointer",
        textDecoration: "none", borderRadius: sizes.radius, width: full ? "100%" : "auto",
        transition: "transform .12s ease, filter .12s ease", whiteSpace: "nowrap",
        ...style,
      }}
      onMouseEnter={(e)=>{ e.currentTarget.style.filter = "brightness(0.96)"; }}
      onMouseLeave={(e)=>{ e.currentTarget.style.filter = "brightness(1)"; }}
      onMouseDown={(e)=>{ e.currentTarget.style.transform = "translateY(1px)"; }}
      onMouseUp={(e)=>{ e.currentTarget.style.transform = "translateY(0)"; }}>
      {icon && <Icon name={icon} size={size === "lg" ? 18 : 16}/>}
      {children}
      {iconRight && <Icon name={iconRight} size={size === "lg" ? 18 : 16}/>}
    </Cmp>
  );
};

const Input = React.forwardRef(({ icon, label, hint, error, suffix, full = true, style, ...rest }, ref) => (
  <label style={{ display: "block", width: full ? "100%" : "auto" }}>
    {label && <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-700)", marginBottom: 6, fontFamily: "var(--font-ui)", letterSpacing: "0.01em" }}>{label}</div>}
    <div style={{
      display: "flex", alignItems: "center", gap: 8,
      padding: "11px 14px", border: `1px solid ${error ? "#C44" : "var(--ink-200)"}`,
      borderRadius: 10, background: "#fff", transition: "border-color .15s",
      ...style
    }}>
      {icon && <Icon name={icon} size={16}/>}
      <input ref={ref} {...rest}
        style={{ border: "none", outline: "none", width: "100%", fontFamily: "var(--font-ui)", fontSize: 14, color: "var(--ink-900)", background: "transparent" }}/>
      {suffix && <span style={{ color: "var(--ink-500)", fontSize: 13, fontFamily: "var(--font-ui)" }}>{suffix}</span>}
    </div>
    {hint && !error && <div style={{ fontSize: 12, color: "var(--ink-500)", marginTop: 6, fontFamily: "var(--font-ui)" }}>{hint}</div>}
    {error && <div style={{ fontSize: 12, color: "#C44", marginTop: 6, fontFamily: "var(--font-ui)" }}>{error}</div>}
  </label>
));

const Chip = ({ active, onClick, children, icon }) => (
  <button onClick={onClick}
    style={{
      display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 12px",
      border: `1px solid ${active ? "var(--coral)" : "var(--ink-200)"}`,
      background: active ? "var(--coral)" : "#fff",
      color: active ? "#fff" : "var(--ink-900)", borderRadius: 999, cursor: "pointer",
      fontFamily: "var(--font-ui)", fontWeight: 500, fontSize: 13, transition: "all .15s"
    }}>
    {icon && <Icon name={icon} size={14}/>}
    {children}
  </button>
);

// ---------- HEADER ----------
const Header = ({ currentPath = "" }) => {
  const [searchOpen, setSearchOpen] = useState(false);
  const [mobileNav, setMobileNav] = useState(false);
  const navItems = [
    { label: "Browse", href: "#/search" },
    { label: "Messages", href: "#/messages" },
    { label: "Saved", href: "#/saved" },
    { label: "Help", href: "#/help" },
  ];
  return (
    <header style={{
      position: "sticky", top: 0, zIndex: 50, background: "rgba(250, 246, 241, 0.92)",
      backdropFilter: "blur(12px)", borderBottom: "1px solid var(--ink-200)",
    }}>
      {/* Announcement strip */}
      <div style={{ background: "var(--ink-900)", color: "#F3E5D8", fontSize: 12.5, padding: "7px 0", textAlign: "center", fontFamily: "var(--font-ui)" }}>
        <span style={{ opacity: 0.85 }}>Buy & sell across Qatar — </span>
        <a href="#/post" style={{ color: "var(--coral)", textDecoration: "none", fontWeight: 600 }}>post your first ad free →</a>
      </div>
      <div className="container" style={{ display: "flex", alignItems: "center", gap: 24, padding: "14px 24px" }}>
        <Logo size={34}/>
        {/* Desktop search */}
        <div className="desktop-only" style={{ flex: 1, maxWidth: 640, position: "relative" }}>
          <div style={{ display: "flex", alignItems: "center", background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 12, paddingLeft: 14, height: 46 }}>
            <Icon name="search" size={18}/>
            <input placeholder="Find anything in Qatar…  iPhone, sofa, Land Cruiser…"
              style={{ flex: 1, border: "none", outline: "none", padding: "0 12px", fontSize: 14, fontFamily: "var(--font-ui)", color: "var(--ink-900)", background: "transparent" }}/>
            <div style={{ display: "flex", alignItems: "center", borderLeft: "1px solid var(--ink-200)", padding: "0 14px", color: "var(--ink-700)", fontSize: 13, fontFamily: "var(--font-ui)", gap: 6, height: 28 }}>
              <Icon name="location" size={14}/>All Qatar
              <Icon name="chevronDown" size={14}/>
            </div>
            <Btn kind="primary" size="sm" style={{ margin: 5, height: 36 }} href="#/search">Search</Btn>
          </div>
        </div>
        <nav className="desktop-only" style={{ display: "flex", alignItems: "center", gap: 4 }}>
          {navItems.map(n => (
            <a key={n.label} href={n.href}
              style={{
                fontSize: 14, fontFamily: "var(--font-ui)", color: "var(--ink-700)", textDecoration: "none",
                padding: "8px 12px", borderRadius: 8, fontWeight: 500
              }}>{n.label}</a>
          ))}
        </nav>
        <div className="desktop-only" style={{ display: "flex", alignItems: "center", gap: 8 }}>
          <a href="#/profile" style={{ padding: 8, borderRadius: 8, color: "var(--ink-700)" }} title="Account"><Icon name="user"/></a>
          <Btn kind="primary" icon="plus" href="#/post">Post an ad</Btn>
        </div>
        {/* Mobile */}
        <div className="mobile-only" style={{ marginLeft: "auto", display: "flex", gap: 6 }}>
          <button onClick={()=>setSearchOpen(v=>!v)} style={{ background: "none", border: "none", padding: 8, cursor: "pointer", color: "var(--ink-700)"}}><Icon name="search"/></button>
          <a href="#/post" style={{ background: "var(--coral)", color: "#fff", display: "inline-flex", alignItems: "center", gap: 4, padding: "8px 12px", borderRadius: 10, textDecoration: "none", fontFamily: "var(--font-ui)", fontWeight: 600, fontSize: 13 }}><Icon name="plus" size={16}/></a>
          <button onClick={()=>setMobileNav(v=>!v)} style={{ background: "none", border: "none", padding: 8, cursor: "pointer", color: "var(--ink-700)"}}><Icon name={mobileNav ? "close" : "menu"}/></button>
        </div>
      </div>
      {/* Mobile search */}
      {searchOpen && (
        <div className="mobile-only" style={{ padding: "0 16px 12px" }}>
          <div style={{ display: "flex", alignItems: "center", background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 12, paddingLeft: 12, height: 42 }}>
            <Icon name="search" size={16}/>
            <input placeholder="Find anything…" style={{ flex: 1, border: "none", outline: "none", padding: "0 8px", fontSize: 14, fontFamily: "var(--font-ui)" }}/>
          </div>
        </div>
      )}
      {mobileNav && (
        <div className="mobile-only" style={{ borderTop: "1px solid var(--ink-200)", padding: "8px 16px 16px" }}>
          {navItems.map(n => (
            <a key={n.label} href={n.href} onClick={()=>setMobileNav(false)}
              style={{ display: "block", padding: "12px 4px", fontFamily: "var(--font-ui)", color: "var(--ink-900)", textDecoration: "none", borderBottom: "1px solid var(--ink-200)" }}>{n.label}</a>
          ))}
          <a href="#/profile" onClick={()=>setMobileNav(false)} style={{ display: "block", padding: "12px 4px", fontFamily: "var(--font-ui)", color: "var(--ink-900)", textDecoration: "none" }}>My account</a>
        </div>
      )}
    </header>
  );
};

// ---------- FOOTER ----------
const Footer = () => (
  <footer style={{ marginTop: 96, background: "var(--ink-900)", color: "#E6DBC9", padding: "64px 0 32px" }}>
    <div className="container" style={{ padding: "0 24px" }}>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 40 }}>
        <div style={{ gridColumn: "span 2", minWidth: 260 }}>
          <Logo size={36} ink="#F3E5D8" stripe="#3A332D"/>
          <p style={{ marginTop: 16, fontFamily: "var(--font-ui)", fontSize: 14, color: "#B6A998", lineHeight: 1.6, maxWidth: 380 }}>
            Bazzar is the friendly classifieds marketplace built for everyday buyers and sellers across Qatar. Post your first ad in under a minute.
          </p>
          <div style={{ display: "flex", gap: 12, marginTop: 20 }}>
            {["facebook","instagram","x","tiktok"].map(n => (
              <a key={n} href="#" style={{ width: 36, height: 36, borderRadius: 999, background: "#3A332D", display: "inline-flex", alignItems: "center", justifyContent: "center", color: "#E6DBC9" }}><Icon name={n} size={16}/></a>
            ))}
          </div>
        </div>
        <FooterCol title="Marketplace" links={[
          ["All Categories", "#/search"],
          ["Featured Ads", "#/search"],
          ["Post an Ad", "#/post"],
          ["Pricing & Plans", "#/help"],
        ]}/>
        <FooterCol title="Account" links={[
          ["My Profile", "#/profile"],
          ["My Ads", "#/profile"],
          ["Messages", "#/messages"],
          ["Saved Ads", "#/saved"],
        ]}/>
        <FooterCol title="Trust & Safety" links={[
          ["Safety Tips", "#/help"],
          ["Posting Policy", "#/help"],
          ["Report a Listing", "#/help"],
          ["Contact Support", "#/help"],
        ]}/>
      </div>
      <div style={{ marginTop: 56, paddingTop: 24, borderTop: "1px solid #3A332D", display: "flex", flexWrap: "wrap", justifyContent: "space-between", gap: 12, fontFamily: "var(--font-ui)", fontSize: 12.5, color: "#8E8174" }}>
        <div>© 2026 Bazzar Qatar. A community-built marketplace.</div>
        <div style={{ display: "flex", gap: 20 }}>
          <a href="#" style={{ color: "#8E8174", textDecoration: "none" }}>Terms</a>
          <a href="#" style={{ color: "#8E8174", textDecoration: "none" }}>Privacy</a>
          <a href="#" style={{ color: "#8E8174", textDecoration: "none" }}>Cookies</a>
          <a href="#" style={{ color: "#8E8174", textDecoration: "none" }}>عربي</a>
        </div>
      </div>
    </div>
  </footer>
);

const FooterCol = ({ title, links }) => (
  <div>
    <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, fontWeight: 700, color: "#F3E5D8", letterSpacing: "0.08em", textTransform: "uppercase", marginBottom: 14 }}>{title}</div>
    <ul style={{ listStyle: "none", padding: 0, margin: 0, display: "flex", flexDirection: "column", gap: 10 }}>
      {links.map(([l, h]) => (
        <li key={l}><a href={h} style={{ fontFamily: "var(--font-ui)", fontSize: 14, color: "#B6A998", textDecoration: "none" }}>{l}</a></li>
      ))}
    </ul>
  </div>
);

// ---------- LISTING CARD ----------
const ListingCard = ({ listing, layout = "grid", onSave, saved = false }) => {
  const { id, title, price, currency, location, postedAgo, featured, category, condition, swatch, thumbs, photo } = listing;
  if (layout === "list") {
    return (
      <a href={`#/detail/${id}`} style={{ display: "flex", gap: 16, padding: 14, background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 14, textDecoration: "none", color: "inherit", transition: "border-color .15s" }}
         onMouseEnter={(e)=>e.currentTarget.style.borderColor = "var(--coral)"}
         onMouseLeave={(e)=>e.currentTarget.style.borderColor = "var(--ink-200)"}>
        <div style={{ width: 180, flexShrink: 0 }}>
          <SwatchImg category={category} swatch={swatch} aspect="4 / 3" idx={Number(id.slice(-1))} photo={photo}/>
        </div>
        <div style={{ flex: 1, display: "flex", flexDirection: "column" }}>
          <div style={{ display: "flex", justifyContent: "space-between", gap: 12, alignItems: "flex-start" }}>
            <div>
              {featured && <Pill kind="featured">Featured</Pill>}
              <div style={{ fontSize: 17, fontWeight: 600, marginTop: featured ? 8 : 0, color: "var(--ink-900)", fontFamily: "var(--font-ui)", lineHeight: 1.3 }}>{title}</div>
            </div>
            <div style={{ fontSize: 22, fontFamily: "'Instrument Serif', serif", color: "var(--terracotta)", whiteSpace: "nowrap" }}>{formatPrice(price)} <span style={{ fontSize: 13, fontFamily: "var(--font-ui)", color: "var(--ink-500)" }}>{currency}</span></div>
          </div>
          <div style={{ fontSize: 13.5, color: "var(--ink-700)", marginTop: 8, fontFamily: "var(--font-ui)" }}>{listing.desc?.slice(0, 140)}…</div>
          <div style={{ marginTop: "auto", paddingTop: 12, display: "flex", gap: 14, fontSize: 13, color: "var(--ink-500)", fontFamily: "var(--font-ui)", alignItems: "center" }}>
            <span style={{ display: "inline-flex", alignItems: "center", gap: 4 }}><Icon name="location" size={13}/>{location}</span>
            <span>·</span>
            <span>{postedAgo} ago</span>
            <span>·</span>
            <span>{condition}</span>
          </div>
        </div>
      </a>
    );
  }
  return (
    <a href={`#/detail/${id}`} className="listing-card" style={{
      display: "flex", flexDirection: "column", background: "#fff",
      border: "1px solid var(--ink-200)", borderRadius: 14, overflow: "hidden",
      textDecoration: "none", color: "inherit", transition: "transform .15s ease, border-color .15s",
    }}
    onMouseEnter={(e) => { e.currentTarget.style.transform = "translateY(-2px)"; e.currentTarget.style.borderColor = "var(--coral)"; }}
    onMouseLeave={(e) => { e.currentTarget.style.transform = "translateY(0)"; e.currentTarget.style.borderColor = "var(--ink-200)"; }}>
      <div style={{ position: "relative" }}>
        <SwatchImg category={category} swatch={swatch} aspect="4 / 3" idx={Number(id.slice(-1))} photo={photo}/>
        {featured && <div style={{ position: "absolute", top: 10, left: 10 }}><Pill kind="featured">Featured</Pill></div>}
        <button onClick={(e)=>{ e.preventDefault(); onSave && onSave(id); }}
          style={{ position: "absolute", top: 10, right: 10, width: 34, height: 34, borderRadius: 999, background: "rgba(255,255,255,.92)", border: "none", display: "inline-flex", alignItems: "center", justifyContent: "center", cursor: "pointer", color: saved ? "var(--coral)" : "var(--ink-700)" }}>
          <Icon name="heart" size={16} stroke={saved ? 0 : 1.7}/>
        </button>
        {thumbs > 1 && (
          <div style={{ position: "absolute", bottom: 8, right: 8, padding: "3px 7px", background: "rgba(42,38,34,0.78)", color: "#fff", borderRadius: 6, fontSize: 11, fontFamily: "var(--font-ui)", display: "inline-flex", gap: 4, alignItems: "center" }}>
            <Icon name="camera" size={11}/> {thumbs}
          </div>
        )}
      </div>
      <div style={{ padding: "14px 14px 16px" }}>
        <div style={{ fontSize: 19, fontFamily: "'Instrument Serif', serif", color: "var(--terracotta)", lineHeight: 1, fontWeight: 400 }}>
          {formatPrice(price)} <span style={{ fontSize: 12, fontFamily: "var(--font-ui)", color: "var(--ink-500)" }}>{currency}</span>
        </div>
        <div style={{ fontSize: 14.5, fontWeight: 600, marginTop: 8, color: "var(--ink-900)", fontFamily: "var(--font-ui)", lineHeight: 1.35, display: "-webkit-box", WebkitLineClamp: 2, WebkitBoxOrient: "vertical", overflow: "hidden", minHeight: 40 }}>{title}</div>
        <div style={{ display: "flex", gap: 8, marginTop: 12, fontSize: 12, color: "var(--ink-500)", fontFamily: "var(--font-ui)" }}>
          <span style={{ display: "inline-flex", alignItems: "center", gap: 3 }}><Icon name="location" size={12}/>{location.split(",")[0]}</span>
          <span>·</span>
          <span>{postedAgo}</span>
        </div>
      </div>
    </a>
  );
};

const Pill = ({ kind = "default", children }) => {
  const styles = {
    featured: { background: "var(--coral)", color: "#fff" },
    default:  { background: "var(--cream-200)", color: "var(--ink-700)" },
    sage:     { background: "#E2EBE2", color: "#3A6B3A" },
    sold:     { background: "var(--ink-900)", color: "#fff" },
  }[kind];
  return <span style={{ ...styles, display: "inline-block", padding: "3px 9px", borderRadius: 999, fontSize: 11, fontWeight: 600, fontFamily: "var(--font-ui)", letterSpacing: "0.02em", textTransform: "uppercase" }}>{children}</span>;
};

// ---------- UTILITIES ----------
const formatPrice = (n) => n.toLocaleString("en-US");

// ---------- SECTION HEADER ----------
const SectionHeader = ({ kicker, title, action, actionHref }) => (
  <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", marginBottom: 24, gap: 16, flexWrap: "wrap" }}>
    <div>
      {kicker && <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, fontWeight: 700, letterSpacing: "0.12em", textTransform: "uppercase", color: "var(--coral)" }}>{kicker}</div>}
      <h2 style={{ margin: kicker ? "6px 0 0" : 0, fontFamily: "'Instrument Serif', serif", fontSize: "clamp(28px, 3.4vw, 40px)", fontWeight: 400, color: "var(--ink-900)", letterSpacing: "-0.015em", lineHeight: 1.1 }}>{title}</h2>
    </div>
    {action && <a href={actionHref || "#"} style={{ fontFamily: "var(--font-ui)", fontSize: 14, fontWeight: 600, color: "var(--coral)", textDecoration: "none", display: "inline-flex", alignItems: "center", gap: 6 }}>{action} <Icon name="chevron" size={14}/></a>}
  </div>
);

// Expose
Object.assign(window, {
  Icon, Logo, SwatchImg, Btn, Input, Chip, Header, Footer, FooterCol,
  ListingCard, Pill, SectionHeader, formatPrice,
});
