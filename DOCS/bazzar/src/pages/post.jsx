// Bazzar — Post a new ad (multi-step form)
const Post = ({ categories, cities }) => {
  const [step, setStep] = useState(1);
  const [category, setCategory] = useState(null);
  const [title, setTitle] = useState("");
  const [desc, setDesc] = useState("");
  const [price, setPrice] = useState("");
  const [condition, setCondition] = useState("");
  const [city, setCity] = useState("");
  const [photos, setPhotos] = useState([0, 1, 2]); // mock photo slots
  const [tier, setTier] = useState("free");
  const steps = ["Category", "Photos", "Details", "Price & Location", "Visibility", "Review"];

  const next = () => setStep(Math.min(6, step + 1));
  const prev = () => setStep(Math.max(1, step - 1));

  return (
    <div className="container" style={{ padding: "32px 24px 0", maxWidth: 900 }}>
      <a href="#/home" style={{ fontFamily: "var(--font-ui)", fontSize: 13, color: "var(--ink-500)", textDecoration: "none", display: "inline-flex", alignItems: "center", gap: 6 }}>
        <Icon name="back" size={14}/> Cancel & go back
      </a>

      <h1 style={{ margin: "16px 0 8px", fontFamily: "'Instrument Serif', serif", fontSize: "clamp(36px, 4.5vw, 52px)", fontWeight: 400, color: "var(--ink-900)", letterSpacing: "-0.015em" }}>
        Post a new ad
      </h1>
      <p style={{ margin: "0 0 32px", fontFamily: "var(--font-ui)", color: "var(--ink-700)", fontSize: 15 }}>
        Most ads go live in under a minute. Step {step} of {steps.length}.
      </p>

      {/* Stepper */}
      <div style={{ display: "flex", gap: 4, marginBottom: 32 }}>
        {steps.map((s, i) => (
          <div key={s} style={{ flex: 1 }}>
            <div style={{ height: 4, borderRadius: 4, background: i < step ? "var(--coral)" : "var(--ink-200)", transition: "background .2s" }}/>
            <div style={{ fontFamily: "var(--font-ui)", fontSize: 11, color: i < step ? "var(--ink-900)" : "var(--ink-500)", fontWeight: i+1 === step ? 700 : 500, marginTop: 6, letterSpacing: "0.04em" }} className="desktop-only">
              0{i+1} {s}
            </div>
          </div>
        ))}
      </div>

      {/* Form card */}
      <div style={{ background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 16, padding: "32px 36px" }}>
        {step === 1 && (
          <>
            <StepTitle n={1} title="What are you selling?" sub="Pick the category that fits best — you can refine later."/>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(160px, 1fr))", gap: 12, marginTop: 24 }}>
              {categories.map(c => (
                <button key={c.id} onClick={()=>setCategory(c.id)} style={{
                  display: "flex", flexDirection: "column", gap: 10, padding: "18px 16px",
                  border: `1.5px solid ${category===c.id?"var(--coral)":"var(--ink-200)"}`,
                  background: category===c.id ? "var(--cream-50)" : "#fff",
                  borderRadius: 12, cursor: "pointer", textAlign: "left", alignItems: "flex-start",
                  fontFamily: "var(--font-ui)",
                }}>
                  <div style={{ width: 36, height: 36, borderRadius: 8, background: "var(--cream-200)", color: "var(--terracotta)", display: "flex", alignItems: "center", justifyContent: "center" }}>
                    <Icon name={c.icon} size={20}/>
                  </div>
                  <div style={{ fontWeight: 600, fontSize: 14, color: "var(--ink-900)" }}>{c.label}</div>
                </button>
              ))}
            </div>
          </>
        )}

        {step === 2 && (
          <>
            <StepTitle n={2} title="Add photos" sub="Up to 20 photos. The first will be your cover. Bright, well-lit photos sell faster."/>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(140px, 1fr))", gap: 10, marginTop: 24 }}>
              {photos.map((p, i) => (
                <div key={i} style={{ position: "relative" }}>
                  <SwatchImg swatch="warm" aspect="1 / 1" idx={i}/>
                  {i === 0 && <div style={{ position: "absolute", top: 8, left: 8 }}><Pill kind="featured">Cover</Pill></div>}
                  <button onClick={()=>setPhotos(photos.filter((_, j) => j !== i))}
                    style={{ position: "absolute", top: 8, right: 8, width: 26, height: 26, borderRadius: 999, background: "rgba(42,38,34,0.78)", border: "none", color: "#fff", display: "flex", alignItems: "center", justifyContent: "center", cursor: "pointer" }}><Icon name="close" size={12}/></button>
                </div>
              ))}
              <button onClick={()=>setPhotos([...photos, photos.length])} style={{
                aspectRatio: "1 / 1", border: "2px dashed var(--ink-200)", background: "var(--cream-50)",
                borderRadius: 10, display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", gap: 8,
                cursor: "pointer", color: "var(--ink-500)", fontFamily: "var(--font-ui)", fontSize: 13,
              }}>
                <Icon name="camera" size={24}/>
                Add photo
              </button>
            </div>
            <div style={{ marginTop: 14, fontFamily: "var(--font-ui)", fontSize: 12.5, color: "var(--ink-500)" }}>
              Tip: drag photos to reorder. The cover photo appears in search results.
            </div>
          </>
        )}

        {step === 3 && (
          <>
            <StepTitle n={3} title="Tell us more" sub="A clear title and honest description get far more interest."/>
            <div style={{ display: "flex", flexDirection: "column", gap: 18, marginTop: 24 }}>
              <Input label="Ad title" placeholder="e.g. iPhone 15 Pro Max 256GB — sealed box" maxLength={70}
                value={title} onChange={e=>setTitle(e.target.value)} hint={`${title.length}/70 characters`}/>
              <div>
                <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-700)", marginBottom: 6, fontFamily: "var(--font-ui)" }}>Description</div>
                <textarea value={desc} onChange={e=>setDesc(e.target.value)} rows={6}
                  placeholder="Be specific about condition, age, included accessories, reason for selling, pickup options…"
                  style={{ width: "100%", padding: "12px 14px", border: "1px solid var(--ink-200)", borderRadius: 10, fontFamily: "var(--font-ui)", fontSize: 14, resize: "vertical", outline: "none" }}/>
                <div style={{ fontSize: 12, color: "var(--ink-500)", marginTop: 6, fontFamily: "var(--font-ui)" }}>{desc.length}/2000 characters</div>
              </div>
              <div>
                <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-700)", marginBottom: 8, fontFamily: "var(--font-ui)" }}>Condition</div>
                <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                  {["Brand new", "Like new", "Used – good", "Used – fair", "For parts"].map(c => (
                    <Chip key={c} active={condition === c} onClick={()=>setCondition(c)}>{c}</Chip>
                  ))}
                </div>
              </div>
            </div>
          </>
        )}

        {step === 4 && (
          <>
            <StepTitle n={4} title="Price & location" sub="Be realistic — fairly-priced ads sell up to 5× faster."/>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginTop: 24 }}>
              <Input label="Price" placeholder="0" type="number" suffix="QAR" value={price} onChange={e=>setPrice(e.target.value)}/>
              <div>
                <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-700)", marginBottom: 6, fontFamily: "var(--font-ui)" }}>Pricing type</div>
                <div style={{ display: "flex", gap: 8 }}>
                  <Chip active>Fixed</Chip>
                  <Chip>Negotiable</Chip>
                  <Chip>Free</Chip>
                  <Chip>Contact for price</Chip>
                </div>
              </div>
              <div style={{ gridColumn: "span 2" }}>
                <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-700)", marginBottom: 6, fontFamily: "var(--font-ui)" }}>City</div>
                <select value={city} onChange={e=>setCity(e.target.value)}
                  style={{ width: "100%", padding: "11px 14px", border: "1px solid var(--ink-200)", borderRadius: 10, fontFamily: "var(--font-ui)", fontSize: 14, background: "#fff" }}>
                  <option value="">Select a city…</option>
                  {cities.map(c => <option key={c}>{c}</option>)}
                </select>
              </div>
              <Input label="Neighbourhood (optional)" placeholder="e.g. West Bay, Marina"/>
              <Input label="Pickup options" placeholder="e.g. Pickup only, or can deliver"/>
            </div>
          </>
        )}

        {step === 5 && (
          <>
            <StepTitle n={5} title="Boost your reach" sub="Free works great — but you can pay to be seen by more buyers."/>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 14, marginTop: 24 }}>
              {[
                { id: "free",     title: "Free",     price: "0 QAR",   tag: "Standard placement in search & category. Visible for 30 days." },
                { id: "bump",     title: "Bump",     price: "25 QAR",  tag: "Bump your ad to the top of search 3 times over 7 days." },
                { id: "featured", title: "Featured", price: "75 QAR",  tag: "Top-of-category badge + homepage carousel for 7 days. 3× views." },
              ].map(t => (
                <button key={t.id} onClick={()=>setTier(t.id)} style={{
                  display: "flex", flexDirection: "column", padding: 20, textAlign: "left",
                  border: `2px solid ${tier===t.id?"var(--coral)":"var(--ink-200)"}`,
                  background: tier===t.id ? "var(--cream-50)" : "#fff",
                  borderRadius: 14, cursor: "pointer", fontFamily: "var(--font-ui)",
                }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                    <span style={{ fontWeight: 700, fontSize: 16, color: "var(--ink-900)" }}>{t.title}</span>
                    {t.id === "featured" && <Pill kind="featured">Most popular</Pill>}
                  </div>
                  <div style={{ fontFamily: "'Instrument Serif', serif", fontSize: 30, color: "var(--terracotta)", marginTop: 6 }}>{t.price}</div>
                  <div style={{ fontSize: 13, color: "var(--ink-700)", marginTop: 12, lineHeight: 1.55 }}>{t.tag}</div>
                </button>
              ))}
            </div>
          </>
        )}

        {step === 6 && (
          <>
            <StepTitle n={6} title="Review and publish" sub="Looks good? Hit publish — you can always edit later."/>
            <div style={{ marginTop: 24, display: "grid", gridTemplateColumns: "180px 1fr", gap: 24, padding: 20, border: "1px solid var(--ink-200)", borderRadius: 14 }}>
              <SwatchImg swatch="warm" aspect="4 / 3"/>
              <div>
                <Pill kind={tier === "featured" ? "featured" : "default"}>{tier === "free" ? "Standard" : tier === "bump" ? "Bumped" : "Featured"}</Pill>
                <h3 style={{ margin: "10px 0 6px", fontFamily: "'Instrument Serif', serif", fontSize: 24, color: "var(--ink-900)", fontWeight: 400 }}>
                  {title || "Your ad title will appear here"}
                </h3>
                <div style={{ fontFamily: "'Instrument Serif', serif", color: "var(--terracotta)", fontSize: 22 }}>
                  {price ? `${formatPrice(Number(price))} QAR` : "0 QAR"}
                </div>
                <div style={{ marginTop: 10, fontFamily: "var(--font-ui)", fontSize: 13, color: "var(--ink-700)" }}>
                  {city || "City"} · {condition || "Condition"} · {categories.find(c=>c.id===category)?.label || "Category"}
                </div>
                <p style={{ marginTop: 12, fontFamily: "var(--font-ui)", fontSize: 14, color: "var(--ink-700)", lineHeight: 1.55 }}>
                  {desc || "Your description will appear here, including all the details you've added."}
                </p>
              </div>
            </div>
            <div style={{ marginTop: 20, padding: 16, background: "var(--cream-200)", borderRadius: 12, display: "flex", gap: 12, fontFamily: "var(--font-ui)", fontSize: 13.5, color: "var(--ink-700)" }}>
              <Icon name="check" size={18}/>
              By publishing, you agree to Bazzar's <a href="#/help" style={{ color: "var(--coral)" }}>Posting Policy</a> and confirm the item is yours to sell.
            </div>
          </>
        )}
      </div>

      {/* Nav */}
      <div style={{ marginTop: 24, display: "flex", justifyContent: "space-between", paddingBottom: 64 }}>
        <Btn kind="ghost" onClick={prev} icon="back" style={{ visibility: step > 1 ? "visible" : "hidden" }}>Back</Btn>
        {step < 6
          ? <Btn kind="primary" onClick={next} iconRight="chevron">Continue</Btn>
          : <Btn kind="deep" iconRight="arrow" href="#/profile">Publish ad</Btn>}
      </div>
    </div>
  );
};

const StepTitle = ({ n, title, sub }) => (
  <div>
    <div style={{ fontFamily: "var(--font-ui)", fontSize: 12, fontWeight: 700, letterSpacing: "0.14em", color: "var(--coral)", textTransform: "uppercase" }}>Step 0{n}</div>
    <h2 style={{ margin: "6px 0 6px", fontFamily: "'Instrument Serif', serif", fontSize: 32, fontWeight: 400, color: "var(--ink-900)", letterSpacing: "-0.01em" }}>{title}</h2>
    <p style={{ margin: 0, fontFamily: "var(--font-ui)", fontSize: 14.5, color: "var(--ink-700)" }}>{sub}</p>
  </div>
);

window.Post = Post;
