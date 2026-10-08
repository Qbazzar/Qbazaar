"use client"

import { useTheme } from "next-themes"
import { Toaster as Sonner, type ToasterProps } from "sonner"
import { CircleCheckIcon, InfoIcon, TriangleAlertIcon, OctagonXIcon, Loader2Icon } from "lucide-react"

import { t } from "@/lib/i18n/messages"

/**
 * Toast colours from the design tokens: each tone's text sits on its soft tint.
 * Warnings take the brand orange, the design's only warm tone.
 */
const TOAST_COLOURS = {
  "--normal-bg": "var(--color-qb-surface)",
  "--normal-text": "var(--color-qb-ink)",
  "--normal-border": "var(--color-qb-line)",
  "--border-radius": "var(--radius-qb-md)",
  "--success-bg": "var(--color-qb-success-soft)",
  "--success-text": "var(--color-qb-success)",
  "--success-border": "var(--color-qb-success)",
  "--info-bg": "var(--color-qb-info-soft)",
  "--info-text": "var(--color-qb-info)",
  "--info-border": "var(--color-qb-info)",
  "--warning-bg": "var(--color-qb-brand-soft)",
  "--warning-text": "var(--color-qb-brand-on-soft)",
  "--warning-border": "var(--color-qb-brand)",
  "--error-bg": "var(--color-qb-danger-soft)",
  "--error-text": "var(--color-qb-danger)",
  "--error-border": "var(--color-qb-danger)",
} as React.CSSProperties

const Toaster = ({ ...props }: ToasterProps) => {
  // The site forces the light theme, which useTheme reports apart from the stored preference.
  const { forcedTheme, theme = "system" } = useTheme()

  return (
    <Sonner
      theme={(forcedTheme ?? theme) as ToasterProps["theme"]}
      className="toaster group"
      containerAriaLabel={t("ui.toasts.region", "الإشعارات")}
      icons={{
        success: (
          <CircleCheckIcon className="size-4" />
        ),
        info: (
          <InfoIcon className="size-4" />
        ),
        warning: (
          <TriangleAlertIcon className="size-4" />
        ),
        error: (
          <OctagonXIcon className="size-4" />
        ),
        loading: (
          <Loader2Icon className="size-4 animate-spin" />
        ),
      }}
      style={TOAST_COLOURS}
      toastOptions={{
        closeButtonAriaLabel: t("ui.close", "إغلاق"),
        classNames: {
          toast: "cn-toast font-qb",
        },
      }}
      {...props}
    />
  )
}

export { Toaster }
