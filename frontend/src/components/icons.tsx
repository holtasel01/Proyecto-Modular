/** Set mínimo de iconos de línea (SVG inline, sin dependencias externas) para
 * el sidebar y acciones puntuales de la app. Trazo consistente 24x24. */

import type { ReactNode, SVGProps } from "react";

type IconProps = SVGProps<SVGSVGElement>;

function base(props: IconProps, children: ReactNode) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.8}
      strokeLinecap="round"
      strokeLinejoin="round"
      width={20}
      height={20}
      {...props}
    >
      {children}
    </svg>
  );
}

export const IconHome = (p: IconProps) =>
  base(p, <path d="M3 11.5 12 4l9 7.5M5 10v10h14V10M9.5 20v-6h5v6" />);

export const IconClock = (p: IconProps) =>
  base(p, <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3.5 2" /></>);

export const IconTrendingUp = (p: IconProps) =>
  base(p, <><path d="M3 17l6-6 4 4 7-8" /><path d="M14 7h6v6" /></>);

export const IconFlask = (p: IconProps) =>
  base(
    p,
    <>
      <path d="M9 3h6" />
      <path d="M10 3v6.5L4.8 18a1.8 1.8 0 0 0 1.5 2.8h11.4a1.8 1.8 0 0 0 1.5-2.8L14 9.5V3" />
      <path d="M7.5 15h9" />
    </>,
  );

export const IconUsers = (p: IconProps) =>
  base(
    p,
    <>
      <circle cx="9" cy="8" r="3.2" />
      <path d="M2.8 19c.6-3 2.8-4.8 6.2-4.8s5.6 1.8 6.2 4.8" />
      <circle cx="17" cy="9" r="2.6" />
      <path d="M15 14.4c2.6.3 4.2 1.9 4.6 4.6" />
    </>,
  );

export const IconCalendarCheck = (p: IconProps) =>
  base(
    p,
    <>
      <rect x="3.5" y="5" width="17" height="16" rx="2" />
      <path d="M3.5 9.5h17" />
      <path d="M8 3v4M16 3v4" />
      <path d="M8.5 14l2 2 4.5-4.5" />
    </>,
  );

export const IconAlertTriangle = (p: IconProps) =>
  base(
    p,
    <>
      <path d="M12 4 2.5 20h19L12 4Z" />
      <path d="M12 10v4" />
      <circle cx="12" cy="17" r="0.4" fill="currentColor" />
    </>,
  );

export const IconCamera = (p: IconProps) =>
  base(
    p,
    <>
      <path d="M4 8h3l1.5-2.5h7L17 8h3a1.5 1.5 0 0 1 1.5 1.5V18a1.5 1.5 0 0 1-1.5 1.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8Z" />
      <circle cx="12" cy="13" r="3.4" />
    </>,
  );

export const IconSettings = (p: IconProps) =>
  base(
    p,
    <>
      <circle cx="12" cy="12" r="3" />
      <path d="M12 2.5v3M12 18.5v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2.5 12h3M18.5 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1" />
    </>,
  );

export const IconBarChart = (p: IconProps) =>
  base(
    p,
    <>
      <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
    </>,
  );

export const IconLogout = (p: IconProps) =>
  base(
    p,
    <>
      <path d="M9 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H9" />
      <path d="M15.5 16l4-4-4-4" />
      <path d="M19 12H9" />
    </>,
  );

export const IconCopy = (p: IconProps) =>
  base(
    p,
    <>
      <rect x="8.5" y="8.5" width="11" height="11" rx="1.5" />
      <path d="M5.5 15.5h-1A1.5 1.5 0 0 1 3 14V5.5A1.5 1.5 0 0 1 4.5 4H13a1.5 1.5 0 0 1 1.5 1.5v1" />
    </>,
  );

export const IconCheck = (p: IconProps) => base(p, <path d="M4 12.5 9.5 18 20 6" />);

export const IconFace = (p: IconProps) =>
  base(
    p,
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="M9 10.5h.01M15 10.5h.01" />
      <path d="M8.5 15c1 1 2.2 1.5 3.5 1.5s2.5-.5 3.5-1.5" />
    </>,
  );
