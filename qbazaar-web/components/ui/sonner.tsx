"use client"

import { useTheme } from "next-themes"
import { Toaster as Sonner, type ToasterProps } from "sonner"
import { CheckIcon, InfoIcon, TriangleAlertIcon, OctagonXIcon, Loader2Icon } from "lucide-react"

import { t } from "@/lib/i18n/messages"

/** As long as the reference's toast stays on screen (chat.js, cropper.js). */
const TOAST_DURATION_MS = 3200

const OFFSET = { top: 92 }
/** sonner's phone layout spans the viewport between the side offsets, so the banner is 92vw wide. */
const MOBILE_OFFSET = { top: 84, left: "4vw", right: "4vw" }

/**
 * The design's notification bar (401:13703, `.qb-toast-design` of chat.js and
 * cropper.js): a mint banner centred 92 px from the top (84 on phones), as
 * wide as its text up to 574 px (92vw on phones). Confirmations take the mint;
 * info, warnings and errors keep their tone tints in the same banner. Warnings
 * take the brand orange, the design's only warm tone.
 */
const TOASTER_STYLE = {
  "--width": "min(574px, 92vw)",
  "--border-radius": "14px",
  "--normal-bg": "var(--color-qb-toast-mint)",
  "--normal-text": "var(--color-qb-toast-mint-ink)",
  "--success-bg": "var(--color-qb-toast-mint)",
  "--success-text": "var(--color-qb-toast-mint-ink)",
  "--info-bg": "var(--color-qb-info-soft)",
  "--info-text": "var(--color-qb-info)",
  "--warning-bg": "var(--color-qb-brand-soft)",
  "--warning-text": "var(--color-qb-brand-on-soft)",
  "--error-bg": "var(--color-qb-danger-soft)",
  "--error-text": "var(--color-qb-danger)",
  // sonner nudges the icon by a few pixels; the reference keeps a plain 12 px gap.
  "--toast-icon-margin-start": "0px",
  "--toast-icon-margin-end": "0px",
  "--toast-svg-margin-start": "0px",
  "--toast-svg-margin-end": "0px",
} as React.CSSProperties

/**
 * The banner itself. sonner's own stylesheet is unlayered, so the rules it
 * already sets need `!` to lose to these utilities. Set per toast type, so a
 * `toast.custom` banner keeps its own box.
 */
const BANNER = [
  "gap-3! border-0! min-h-[52px] px-[18px]! py-3! text-qb-body-sm! shadow-qb-toast!",
  "focus-visible:outline-solid! focus-visible:outline-2! focus-visible:outline-offset-2! focus-visible:outline-qb-brand-active!",
  "qb-tablet:min-h-[62px] qb-tablet:px-11! qb-tablet:py-3.5! qb-tablet:text-qb-h5!",
  "qb-tablet:inset-x-0 qb-tablet:mx-auto qb-tablet:w-fit!",
].join(" ")

const ICON = "size-[22px]"

const Toaster = ({ ...props }: ToasterProps) => {
  // The site forces the light theme, which useTheme reports apart from the stored preference.
  const { forcedTheme, theme = "system" } = useTheme()

  return (
    <Sonner
      theme={(forcedTheme ?? theme) as ToasterProps["theme"]}
      className="toaster group"
      containerAriaLabel={t("ui.toasts.region", "الإشعارات")}
      position="top-center"
      offset={OFFSET}
      mobileOffset={MOBILE_OFFSET}
      duration={TOAST_DURATION_MS}
      richColors
      icons={{
        success: <CheckIcon className={ICON} />,
        info: <InfoIcon className={ICON} />,
        warning: <TriangleAlertIcon className={ICON} />,
        error: <OctagonXIcon className={ICON} />,
        loading: <Loader2Icon className={`${ICON} animate-spin`} />,
      }}
      style={TOASTER_STYLE}
      toastOptions={{
        closeButtonAriaLabel: t("ui.close", "إغلاق"),
        classNames: {
          toast: "cn-toast font-qb",
          icon: "size-[22px]!",
          success: BANNER,
          info: BANNER,
          warning: BANNER,
          error: BANNER,
          loading: BANNER,
        },
      }}
      {...props}
    />
  )
}

export { Toaster }
