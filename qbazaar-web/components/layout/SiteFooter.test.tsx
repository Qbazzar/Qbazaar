import { readdirSync } from "node:fs";
import path from "node:path";

import { render, screen, within } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { setClientLocale } from "@/lib/i18n/locale";

import { FOOTER_CMS_SLUGS } from "./footer-links";
import { SiteFooter } from "./SiteFooter";
import { SiteFooterGate } from "./SiteFooterGate";

const pathname = vi.hoisted(() => ({ current: "/" }));
vi.mock("next/navigation", () => ({ usePathname: () => pathname.current }));

const APP_DIR = path.resolve(__dirname, "../../app");
const PAGE_FILE = /^page\.(tsx|ts|jsx|js)$/;

/** The URL segments of every page under app/, route groups removed and dynamic segments kept as [x]. */
function appRoutes(dir = APP_DIR, segments: string[] = []): string[][] {
  return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    if (entry.isFile()) return PAGE_FILE.test(entry.name) ? [segments] : [];
    const isGroup = entry.name.startsWith("(") && entry.name.endsWith(")");
    return appRoutes(
      path.join(dir, entry.name),
      isGroup ? segments : [...segments, entry.name],
    );
  });
}

function routeExists(href: string, routes: string[][]) {
  const parts = href.split(/[?#]/)[0].split("/").filter(Boolean);
  return routes.some(
    (route) =>
      route.length === parts.length &&
      route.every((seg, i) => seg.startsWith("[") || seg === parts[i]),
  );
}

describe("SiteFooter", () => {
  beforeEach(() => setClientLocale("en"));

  it("points every link at a page of the app", () => {
    render(<SiteFooter />);
    const routes = appRoutes();
    const hrefs = within(screen.getByRole("contentinfo"))
      .getAllByRole("link")
      .map((link) => link.getAttribute("href") ?? "");

    expect(hrefs.length).toBeGreaterThan(0);
    expect(hrefs.filter((href) => !routeExists(href, routes))).toEqual([]);
  });

  it("renders every column as a heading list and as a collapsible row", () => {
    const { container } = render(<SiteFooter />);
    const titles = ["Classifieds", "Information", "Businesses", "General"];

    expect(
      screen.getByRole("navigation", { name: "Site links" }),
    ).toBeInTheDocument();
    expect(
      screen.getAllByRole("heading", { level: 2 }).map((h) => h.textContent),
    ).toEqual(titles);
    expect(
      [...container.querySelectorAll("details > summary")].map(
        (s) => s.textContent,
      ),
    ).toEqual(titles);
  });

  it("lists the design links of each column in the design order, with the right hrefs", () => {
    const { container } = render(<SiteFooter />);
    const expected: [string, string][][] = [
      [
        ["About Us", "/p/about"],
        ["Careers", "/p/careers"],
        ["Press", "/p/press"],
        ["Classifieds Magazine", "/p/magazine"],
        ["Connect", "/p/connect"],
        ["Mobile Apps", "/p/mobile-apps"],
      ],
      [
        ["Help", "/help"],
        ["Safety Tips", "/p/safety-tips"],
        ["Report a Vulnerability", "/p/report-vulnerability"],
        ["Youth Protection", "/p/youth-protection"],
        ["Accessibility Statement", "/p/accessibility"],
        ["Privacy Policy", "/p/privacy"],
        ["Privacy Settings", "/p/privacy-settings"],
        ["Terms of Use", "/p/terms"],
        ["Legal Notice", "/p/legal-notice"],
      ],
      [
        ["Real Estate Classifieds", "/p/real-estate-classifieds"],
        ["PRO Info Point", "/p/pro-info"],
        ["PRO Packages", "/p/pro-packages"],
        ["Advertise with Us", "/p/advertise"],
      ],
      [
        ["Popular Searches", "/p/popular-searches"],
        ["View Overview", "/p/overview"],
        ["Company Pages Overview", "/companies"],
        ["Vehicle Appraisal", "/p/vehicle-appraisal"],
      ],
    ];
    const columns = [...container.querySelectorAll("details")];

    expect(columns).toHaveLength(expected.length);
    columns.forEach((column, i) => {
      const links = [...column.querySelectorAll("a")].map((a) => [
        a.textContent,
        a.getAttribute("href"),
      ]);
      expect(links).toEqual(expected[i]);
    });
  });

  it("only links CMS pages from the single slug list", () => {
    render(<SiteFooter />);
    const slugs = within(screen.getByRole("contentinfo"))
      .getAllByRole("link")
      .map((link) => link.getAttribute("href") ?? "")
      .filter((href) => href.startsWith("/p/"))
      .map((href) => href.slice("/p/".length));

    expect(slugs.length).toBeGreaterThan(0);
    expect(slugs.every((slug) => FOOTER_CMS_SLUGS.includes(slug))).toBe(true);
  });

  it("links only real routes and keeps the social icons decorative until they have URLs", () => {
    render(<SiteFooter />);
    const links = within(screen.getByRole("contentinfo")).getAllByRole("link");

    expect(
      screen.getAllByRole("link", { name: "Privacy Policy" })[0],
    ).toHaveAttribute("href", "/p/privacy");
    expect(
      screen.getAllByRole("link", { name: "Company Pages Overview" })[0],
    ).toHaveAttribute("href", "/companies");
    expect(
      links.every((link) => link.getAttribute("href")?.startsWith("/")),
    ).toBe(true);
    expect(screen.queryByRole("link", { name: "Instagram" })).toBeNull();
    expect(screen.getByText("Q BAZAAR")).toHaveClass("font-qb-brand");
  });
});

describe("SiteFooterGate", () => {
  it.each([
    ["/", true],
    ["/ads/1", true],
    ["/post-ad", true],
    ["/login", false],
    ["/verify-email", false],
  ])("on %s shows the footer: %s", (path, shown) => {
    pathname.current = path;
    render(
      <SiteFooterGate>
        <footer>footer</footer>
      </SiteFooterGate>,
    );

    expect(screen.queryByText("footer") !== null).toBe(shown);
  });
});
