// Bazzar — main app shell, router, tweaks integration

const BAZZAR_DEFAULTS = /*EDITMODE-BEGIN*/{
  "homeVariant": "A",
  "palette": "coral",
  "showAnnouncement": true,
  "density": "comfortable"
}/*EDITMODE-END*/;

const PALETTES = {
  coral:     { coral: "#EE8765", terracotta: "#B85A45" },
  saffron:   { coral: "#E8A547", terracotta: "#A8682A" },
  rose:      { coral: "#D9697D", terracotta: "#94405A" },
  forest:    { coral: "#6B9A6E", terracotta: "#3A6B40" },
};

function parseRoute() {
  const hash = window.location.hash.replace(/^#\/?/, "") || "home";
  const [path, qs] = hash.split("?");
  const segs = path.split("/").filter(Boolean);
  const params = {};
  if (qs) qs.split("&").forEach(kv => { const [k, v] = kv.split("="); params[k] = decodeURIComponent(v||""); });
  return { name: segs[0] || "home", id: segs[1], params };
}

const App = () => {
  const [tweaks, setTweaks] = useTweaks(BAZZAR_DEFAULTS);
  const [route, setRoute] = useState(parseRoute());
  const [savedIds, setSavedIds] = useState([]);

  useEffect(() => {
    const onHash = () => { setRoute(parseRoute()); window.scrollTo({top:0, behavior:"instant"}); };
    window.addEventListener("hashchange", onHash);
    return () => window.removeEventListener("hashchange", onHash);
  }, []);

  // Apply palette
  useEffect(() => {
    const p = PALETTES[tweaks.palette] || PALETTES.coral;
    document.documentElement.style.setProperty("--coral", p.coral);
    document.documentElement.style.setProperty("--terracotta", p.terracotta);
  }, [tweaks.palette]);

  const onSave = (id) => {
    setSavedIds(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  };

  const sharedProps = {
    listings: window.BAZZAR_LISTINGS,
    categories: window.BAZZAR_CATEGORIES,
    cities: window.BAZZAR_CITIES,
    threads: window.BAZZAR_THREADS,
    faq: window.BAZZAR_FAQ,
    savedIds,
    onSave,
  };

  let page = null;
  switch (route.name) {
    case "home":     page = <Home variant={tweaks.homeVariant} {...sharedProps}/>; break;
    case "search":   page = <Search {...sharedProps}/>; break;
    case "detail":   page = <Detail listingId={route.id} {...sharedProps}/>; break;
    case "post":     page = <Post {...sharedProps}/>; break;
    case "messages": page = <Messages {...sharedProps}/>; break;
    case "profile":  page = <Profile {...sharedProps}/>; break;
    case "saved":    page = <Saved {...sharedProps}/>; break;
    case "signin":   page = <Auth mode="signin"/>; break;
    case "signup":   page = <Auth mode="signup"/>; break;
    case "help":     page = <Help {...sharedProps}/>; break;
    default:         page = <Home variant={tweaks.homeVariant} {...sharedProps}/>;
  }

  return (
    <>
      <Header currentPath={route.name}/>
      <main data-screen-label={`${route.name.charAt(0).toUpperCase()}${route.name.slice(1)}`}>
        {page}
      </main>
      <Footer/>

      {/* Page-jump helper FAB (visible on every page) */}
      <PageJumper currentPath={route.name}/>

      <TweaksPanel>
        <TweakSection label="Homepage variant">
          <TweakRadio
            label="Layout"
            value={tweaks.homeVariant}
            options={[
              { value: "A", label: "Hero" },
              { value: "B", label: "Mag" },
              { value: "C", label: "Util" },
            ]}
            onChange={(v)=>setTweaks("homeVariant", v)}/>
          <div style={{ fontSize: 11, color: "var(--ink-500)", marginTop: 6, fontFamily: "var(--font-ui)" }}>
            Switch between three homepage layouts. Only affects #/home.
          </div>
        </TweakSection>
        <TweakSection label="Brand palette">
          <TweakSelect
            label="Palette"
            value={tweaks.palette}
            options={[
              { value: "coral",   label: "Coral (default)" },
              { value: "saffron", label: "Saffron" },
              { value: "rose",    label: "Rose" },
              { value: "forest",  label: "Forest" },
            ]}
            onChange={(v)=>setTweaks("palette", v)}/>
          <div style={{ display: "flex", gap: 8, marginTop: 10 }}>
            {Object.entries(PALETTES).map(([k, p]) => (
              <button key={k} onClick={()=>setTweaks("palette", k)} title={k}
                style={{ width: 30, height: 30, borderRadius: 8, border: tweaks.palette === k ? "2px solid var(--ink-900)" : "2px solid transparent", background: `linear-gradient(135deg, ${p.coral} 50%, ${p.terracotta} 50%)`, cursor: "pointer" }}/>
            ))}
          </div>
        </TweakSection>
        <TweakSection label="Quick navigation">
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 6 }}>
            {[
              ["Home","home"], ["Search","search"], ["Ad detail","detail/L-2401"],
              ["Post ad","post"], ["Messages","messages"], ["Profile","profile"],
              ["Saved","saved"], ["Sign in","signin"], ["Help/FAQ","help"],
            ].map(([l, h]) => (
              <a key={h} href={`#/${h}`} style={{ padding: "8px 10px", border: "1px solid var(--ink-200)", borderRadius: 8, textAlign: "center", fontFamily: "var(--font-ui)", fontSize: 12, textDecoration: "none", color: "var(--ink-900)", background: "#fff" }}>{l}</a>
            ))}
          </div>
        </TweakSection>
      </TweaksPanel>
    </>
  );
};

// Floating page jumper — always visible, lets reviewers hop pages without tweaks toggle
const PageJumper = ({ currentPath }) => {
  const [open, setOpen] = useState(false);
  const pages = [
    ["Home","home","home"], ["Browse","search","grid"], ["Ad detail","detail/L-2401","eye"],
    ["Post an ad","post","plus"], ["Messages","messages","chat"], ["Profile","profile","user"],
    ["Saved","saved","heart"], ["Sign in","signin","shield"], ["Help","help","bell"],
  ];
  return (
    <div style={{ position: "fixed", bottom: 20, left: 20, zIndex: 100 }}>
      {open && (
        <div style={{ marginBottom: 10, background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 14, padding: 8, boxShadow: "0 12px 32px rgba(42,38,34,0.18)", minWidth: 200 }}>
          <div style={{ padding: "8px 10px 6px", fontFamily: "var(--font-ui)", fontSize: 11, fontWeight: 700, color: "var(--ink-500)", letterSpacing: "0.08em", textTransform: "uppercase" }}>Jump to page</div>
          {pages.map(([l, h, ic]) => {
            const active = currentPath === h.split("/")[0];
            return (
              <a key={h} href={`#/${h}`} onClick={()=>setOpen(false)} style={{
                display: "flex", gap: 10, alignItems: "center", padding: "8px 10px",
                borderRadius: 8, fontFamily: "var(--font-ui)", fontSize: 13, fontWeight: active ? 600 : 500,
                color: active ? "var(--coral)" : "var(--ink-900)", textDecoration: "none",
                background: active ? "var(--cream-50)" : "transparent",
              }}>
                <Icon name={ic} size={14}/>{l}
              </a>
            );
          })}
        </div>
      )}
      <button onClick={()=>setOpen(v=>!v)} style={{
        background: "var(--ink-900)", color: "#FFF7EE", border: "none",
        width: 48, height: 48, borderRadius: 999, cursor: "pointer", display: "flex",
        alignItems: "center", justifyContent: "center", boxShadow: "0 8px 24px rgba(42,38,34,0.25)",
      }}>
        <Icon name={open ? "close" : "menu"} size={20}/>
      </button>
    </div>
  );
};

ReactDOM.createRoot(document.getElementById("root")).render(<App/>);
