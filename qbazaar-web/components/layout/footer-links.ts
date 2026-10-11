/**
 * Footer columns of the design (assets/app.js footerColsRaw): labels, order
 * and titles. Hrefs point at existing routes, or at a CMS page /p/{slug}.
 */
export interface FooterLinkDef {
  href: string;
  /** Key under layout.footer.links. */
  key: string;
}

export interface FooterColumnDef {
  /** Key under layout.footer.cols. */
  titleKey: string;
  links: FooterLinkDef[];
}

const cms = (slug: string, key: string): FooterLinkDef => ({
  href: `/p/${slug}`,
  key,
});

export const FOOTER_COLUMNS: FooterColumnDef[] = [
  {
    titleKey: 'classifieds',
    links: [
      cms('about', 'about'),
      cms('careers', 'careers'),
      cms('press', 'press'),
      cms('magazine', 'magazine'),
      cms('connect', 'connect'),
      cms('mobile-apps', 'mobile_apps'),
    ],
  },
  {
    titleKey: 'information',
    links: [
      { href: '/help', key: 'help' },
      cms('safety-tips', 'safety_tips'),
      cms('report-vulnerability', 'report_vulnerability'),
      cms('youth-protection', 'youth_protection'),
      cms('accessibility', 'accessibility'),
      cms('privacy', 'privacy'),
      cms('privacy-settings', 'privacy_settings'),
      cms('terms', 'terms'),
      cms('legal-notice', 'legal_notice'),
    ],
  },
  {
    titleKey: 'businesses',
    links: [
      cms('real-estate-classifieds', 'real_estate'),
      cms('pro-info', 'pro_info'),
      cms('pro-packages', 'pro_packages'),
      cms('advertise', 'advertise'),
    ],
  },
  {
    titleKey: 'general',
    links: [
      cms('popular-searches', 'popular_searches'),
      cms('overview', 'overview'),
      { href: '/companies', key: 'company_pages' },
      cms('vehicle-appraisal', 'vehicle_appraisal'),
    ],
  },
];

/** The single list of CMS slugs the footer may link to. */
export const FOOTER_CMS_SLUGS: string[] = FOOTER_COLUMNS.flatMap((column) =>
  column.links.filter((link) => link.href.startsWith('/p/')).map((link) => link.href.slice('/p/'.length)),
);
