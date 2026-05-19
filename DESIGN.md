---
name: Tripistry
colors:
  surface: '#FFFFFF'
  surface-dim: '#cfdbf1'
  surface-bright: '#f9f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff3ff'
  surface-container: '#e6eeff'
  surface-container-high: '#dde9ff'
  surface-container-highest: '#d7e3fa'
  on-surface: '#101c2c'
  on-surface-variant: '#5b403f'
  inverse-surface: '#253142'
  inverse-on-surface: '#ebf1ff'
  outline: '#8f6f6e'
  outline-variant: '#e4bebc'
  surface-tint: '#bb152c'
  primary: '#b7102a'
  on-primary: '#ffffff'
  primary-container: '#db313f'
  on-primary-container: '#fffbff'
  inverse-primary: '#ffb3b1'
  secondary: '#485f84'
  on-secondary: '#ffffff'
  secondary-container: '#bbd3fd'
  on-secondary-container: '#445a7f'
  tertiary: '#286182'
  on-tertiary: '#ffffff'
  tertiary-container: '#447a9c'
  on-tertiary-container: '#fcfcff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffdad8'
  primary-fixed-dim: '#ffb3b1'
  on-primary-fixed: '#410007'
  on-primary-fixed-variant: '#92001c'
  secondary-fixed: '#d5e3ff'
  secondary-fixed-dim: '#b0c7f1'
  on-secondary-fixed: '#001b3c'
  on-secondary-fixed-variant: '#30476a'
  tertiary-fixed: '#c7e7ff'
  tertiary-fixed-dim: '#98cdf2'
  on-tertiary-fixed: '#001e2e'
  on-tertiary-fixed-variant: '#064c6b'
  background: '#f9f9ff'
  on-background: '#101c2c'
  surface-variant: '#d7e3fa'
  background-light: '#F8F9FA'
  text-main: '#1D3557'
  muted: '#8D99AE'
  accent: '#457B9D'
typography:
  display-lg:
    fontFamily: Epilogue
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Epilogue
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  body-lg:
    fontFamily: Manrope
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Manrope
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  label-md:
    fontFamily: Manrope
    fontSize: 14px
    fontWeight: '500'
    lineHeight: '1.2'
  button-text:
    fontFamily: Manrope
    fontSize: 15px
    fontWeight: '600'
    lineHeight: '1'
    letterSpacing: 0.01em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  container-padding-desktop: 4rem
  container-padding-mobile: 1.5rem
  stack-gap-lg: 2rem
  stack-gap-md: 1.25rem
  stack-gap-sm: 0.5rem
  input-height: 48px
---

## Brand & Style
Tripistry is a premium travel platform that blends the serenity of high-end exploration with precise, modern utility. The brand personality is **sophisticated, exclusive, and reliable**, targeting discerning travelers and luxury travel agencies.

The visual style is **Modern Corporate with Minimalist influences**, emphasizing high-quality typography and strategic use of whitespace. The interface avoids unnecessary decoration, relying instead on a refined color palette and subtle elevation to create a sense of calm and professional efficiency. The overall emotional response should be one of "effortless luxury"—where the digital experience is as smooth and inviting as a mountain lodge at twilight.

## Colors
The color palette is anchored by a vibrant **Primary Red (#E63946)**, used for critical actions and brand recognition. This is balanced by **Deep Navy (#1D3557)**, which serves as the primary text color and secondary brand anchor, providing high legibility and a sense of authority.

- **Primary:** Used for primary buttons, links, and brand-heavy elements.
- **Secondary (Text-Main):** Used for headings and primary body text to provide a sophisticated alternative to pure black.
- **Tertiary (Accent):** A muted blue-grey used for focus states and secondary UI indicators.
- **Neutrals:** A range of greys (Muted #8D99AE) used for borders, placeholders, and secondary labels.
- **Surface:** Pure white is used for the main interaction containers to maintain a clean, airy feel.

## Typography
The typography system uses a pairing of **Epilogue** (substituting for Clash Display) for headings and **Manrope** (substituting for Satoshi) for body and labels.

**Epilogue** provides a geometric, editorial character for headlines, making them feel distinctive and premium. **Manrope** offers exceptional legibility and a modern, technical feel for interface elements and long-form text. 

For mobile devices, headline sizes should scale down; for instance, a 32px display heading should move to 24px or 28px to ensure the hierarchy remains clear without overwhelming the viewport.

## Layout & Spacing
The system utilizes a **Fixed Grid** approach for desktop views, centering content within a max-width container (typically 400px for forms, or larger for dashboard views), while employing a **Fluid Grid** for mobile devices.

- **Grid:** A 12-column grid is standard for complex layouts, while authentication and simple capture screens use centered vertical stacks.
- **Rhythm:** An 8px base unit drives the spacing. Gaps between form fields are consistently 20px (stack-gap-md), while larger sections are separated by 32px (stack-gap-lg).
- **Margins:** Desktop views feature generous 64px (4rem) side margins, creating a focused, high-end feel. Mobile transitions to a 24px (1.5rem) margin to maximize screen real estate.

## Elevation & Depth
Elevation is handled through a mix of **Tonal Layers** and **Ambient Shadows**.

1. **Surface Tiers:** Backgrounds use a light grey (#F8F9FA), while interactive containers and inputs use pure white (#FFFFFF) to stand out visually without needing heavy shadows.
2. **Shadow Character:** Primary actions (buttons) use a tinted shadow—specifically a soft, diffused red (#E63946 at 25% opacity) to create a "glow" effect that feels energetic and modern. 
3. **Interactive Depth:** Buttons use a subtle Y-axis translation (-1px) on hover combined with an increased shadow spread to simulate physical lift.
4. **Borders:** Secondary elevation (like inputs) is defined by low-contrast outlines (Muted/50) rather than shadows, keeping the UI flat and clean until interaction occurs.

## Shapes
The shape language is consistently **Rounded**, using an 8px (0.5rem) radius for most UI components including input fields and buttons. 

- **Small elements:** Checkboxes use a 4px radius for a sharper, more precise look.
- **Large containers:** Segmented controls and card containers use a 12px (rounded-xl) radius to soften the overall layout and emphasize the "welcoming" brand personality.
- **Interactive elements:** Buttons and Inputs share the exact same radius to maintain visual harmony in form layouts.

## Components
- **Buttons:** Primary buttons are solid-filled (#E63946) with white text, using a 48px height and a custom tinted shadow. Text is semi-bold and slightly larger (15px) than standard body text.
- **Input Fields:** 48px height, 8px border radius, with a 1px border (#8D99AE at 50% opacity). On focus, the border transitions to the Accent color (#457B9D).
- **Segmented Control:** Used for high-level mode switching (e.g., Traveller vs. Agency). Uses a light grey track with a white "sliding" pill that has a subtle 1px shadow for depth.
- **Checkboxes:** Small, 16px squares with a 4px radius. They use the Primary color when checked to maintain brand consistency.
- **Labels:** Positioned above inputs, using Manrope Medium (500 weight) at 14px to ensure they are distinct from the input text itself.
- **Cards (Contextual):** Should follow the 12px corner radius and use a light tonal background or a very subtle 1px border.