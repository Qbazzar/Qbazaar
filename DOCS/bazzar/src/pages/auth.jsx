// Bazzar — Sign in / Sign up
const Auth = ({ mode = "signin" }) => {
  const [tab, setTab] = useState(mode);
  return (
    <div className="container" style={{ padding: "40px 24px 80px", maxWidth: 1100 }}>
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", background: "#fff", border: "1px solid var(--ink-200)", borderRadius: 20, overflow: "hidden", minHeight: 600 }}>
        {/* Side image / pitch */}
        <div className="desktop-only" style={{ background: "var(--terracotta)", color: "#FFF7EE", padding: 48, display: "flex", flexDirection: "column", justifyContent: "space-between", position: "relative", overflow: "hidden" }}>
          <Logo size={36} ink="#FFF7EE" stripe="#3A332D"/>
          <div style={{ position: "relative", zIndex: 1 }}>
            <h2 style={{ margin: 0, fontFamily: "'Instrument Serif', serif", fontSize: 44, fontWeight: 400, lineHeight: 1.05, letterSpacing: "-0.015em" }}>
              Your neighbourhood<br/><em>market awaits.</em>
            </h2>
            <p style={{ marginTop: 16, fontFamily: "var(--font-ui)", fontSize: 15, lineHeight: 1.55, opacity: 0.9 }}>
              Join 42,000 Qatari neighbours buying, selling, and finding their next favourite thing.
            </p>
            <div style={{ marginTop: 32, display: "flex", flexDirection: "column", gap: 12, fontFamily: "var(--font-ui)", fontSize: 14 }}>
              {["Free to post — always", "Verified sellers, real reviews", "Built for Qatar, in your time zone"].map(t => (
                <div key={t} style={{ display: "flex", gap: 10, alignItems: "center" }}><Icon name="check" size={16}/>{t}</div>
              ))}
            </div>
          </div>
          <div style={{ position: "absolute", right: -80, bottom: -80, opacity: 0.15 }}>
            <Logo size={400} withWordmark={false}/>
          </div>
        </div>

        {/* Form */}
        <div style={{ padding: "48px 56px", display: "flex", flexDirection: "column", justifyContent: "center" }}>
          <div style={{ display: "flex", gap: 4, marginBottom: 28, background: "var(--cream-200)", padding: 4, borderRadius: 10 }}>
            {[
              { id: "signin", label: "Sign in" },
              { id: "signup", label: "Create account" },
            ].map(t => (
              <button key={t.id} onClick={()=>setTab(t.id)} style={{
                flex: 1, padding: "10px 16px", borderRadius: 8,
                background: tab === t.id ? "#fff" : "transparent",
                border: "none", fontFamily: "var(--font-ui)", fontWeight: 600, fontSize: 14,
                color: tab === t.id ? "var(--ink-900)" : "var(--ink-500)", cursor: "pointer",
              }}>{t.label}</button>
            ))}
          </div>

          <h1 style={{ margin: "0 0 8px", fontFamily: "'Instrument Serif', serif", fontSize: 36, fontWeight: 400, color: "var(--ink-900)" }}>
            {tab === "signin" ? "Welcome back" : "Create your account"}
          </h1>
          <p style={{ margin: "0 0 28px", fontFamily: "var(--font-ui)", fontSize: 14.5, color: "var(--ink-700)" }}>
            {tab === "signin" ? "Sign in to your Bazzar account." : "It takes less than a minute."}
          </p>

          <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
            {tab === "signup" && <Input label="Full name" placeholder="Sara Al-Mansoori"/>}
            <Input label="Email" placeholder="you@example.com" type="email" icon="user"/>
            {tab === "signup" && (
              <div>
                <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-700)", marginBottom: 6, fontFamily: "var(--font-ui)" }}>Phone number</div>
                <div style={{ display: "flex", gap: 8 }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 4, padding: "11px 14px", border: "1px solid var(--ink-200)", borderRadius: 10, fontFamily: "var(--font-ui)", fontSize: 14, background: "#fff" }}>🇶🇦 +974</div>
                  <input placeholder="5512 4488" style={{ flex: 1, padding: "11px 14px", border: "1px solid var(--ink-200)", borderRadius: 10, fontFamily: "var(--font-ui)", fontSize: 14, outline: "none" }}/>
                </div>
              </div>
            )}
            <Input label="Password" placeholder="••••••••" type="password"
              hint={tab === "signup" ? "At least 8 characters with a number." : undefined}/>
            {tab === "signin" && (
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                <label style={{ display: "flex", gap: 8, alignItems: "center", fontFamily: "var(--font-ui)", fontSize: 13, color: "var(--ink-700)" }}>
                  <input type="checkbox" style={{ accentColor: "var(--coral)" }}/>Keep me signed in
                </label>
                <a href="#" style={{ fontFamily: "var(--font-ui)", fontSize: 13, color: "var(--coral)", textDecoration: "none" }}>Forgot password?</a>
              </div>
            )}
            <Btn kind="primary" size="lg" full href="#/home">{tab === "signin" ? "Sign in" : "Create account"}</Btn>
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: 12, margin: "24px 0", fontFamily: "var(--font-ui)", fontSize: 12, color: "var(--ink-500)" }}>
            <div style={{ flex: 1, height: 1, background: "var(--ink-200)" }}/>
            or continue with
            <div style={{ flex: 1, height: 1, background: "var(--ink-200)" }}/>
          </div>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
            <Btn kind="ghost">Google</Btn>
            <Btn kind="ghost">Apple</Btn>
          </div>

          {tab === "signup" && (
            <p style={{ marginTop: 20, fontFamily: "var(--font-ui)", fontSize: 12, color: "var(--ink-500)", lineHeight: 1.55 }}>
              By creating an account you agree to Bazzar's <a href="#/help" style={{ color: "var(--coral)" }}>Terms of Service</a> and <a href="#/help" style={{ color: "var(--coral)" }}>Privacy Policy</a>.
            </p>
          )}
        </div>
      </div>
    </div>
  );
};

window.Auth = Auth;
