# VXOST Dashboard v2

The first public release of VXOST for macOS: the app, the dashboard, the
documentation pages and three new tools, all sharing one dark, developer
oriented design system built around the official VXOST orange.

No design framework, no CSS or JS dependency, no external request at runtime.
Every icon is inline SVG or a CSS mask.

## What is in here

| Path | What it does |
|------|--------------|
| `dashboard/WHATSNEW.txt` | Full release notes: what changed, install steps, permissions, platforms |
| `dashboard/` | The dashboard itself, in 15 languages |
| `dashboard/stylesheets/all.css` | The whole design system: tokens, grid, components |
| `dashboard/javascripts/all.js` | Theme, mobile menu, language switcher, accordion, reveal |
| `dashboard/includes/layout.php` | Shared header and footer for the PHP pages |
| `dashboard/ports.php` | Listening TCP ports, the process behind each one, local addresses |
| `dashboard/database.php` | phpMyAdmin framed inside the dashboard shell |
| `dashboard/phpinfo.php` | `phpinfo()` output, split into collapsible sections |
| `progetti/index.php` | Index of the projects served from this web root |

## Design system

Colours are taken from the official VXOST gradient, sampled off the brand
artwork. Every pair was checked against WCAG contrast ratios before being
used:

| Token | Dark | Ratio | Light | Purpose |
|-------|------|-------|-------|---------|
| `--accent` | `#FD47FD` | 7.02:1 AAA | `#5A15C9` | actions, active state |
| `--cyan` | `#C79BFF` | 8.95:1 AAA | `#6B21B8` | data and databases |
| `--violet` | `#FC8A7E` | 8.49:1 AAA | `#B03A1E` | code and tooling |
| `--amber` | `#FA8406` | 7.83:1 AAA | `#7E4207` | advisory blocks |
| `--danger` | `#FF5C5C` | 6.49:1 AA  | `#A3200D` | failures |

Dark theme is the default; the light theme is a token override, switched from
the navigation bar and remembered in `localStorage`.

## Accessibility

Skip link and `<main>` landmark on every page, visible focus rings, touch
targets of at least 44px, `aria-current` on the active menu entry, keyboard
support for the accordion and the language menu, and `prefers-reduced-motion`
honoured throughout.

## Languages

15 locales: English, Italian, German, Spanish, French, Brazilian Portuguese,
Romanian, Hungarian, Polish, Russian, Turkish, Japanese, Simplified Chinese,
Traditional Chinese and Urdu, the last one rendered right to left.

The homepages and the HOW-TO indexes are generated from one template plus a
string table, so a wording fix touches a single line. The guides under
`dashboard/docs/` are only available in English, as upstream, and each card
says so with an `EN` badge.

## Installing

Copy the contents of this repository over `vxostfiles/htdocs`, keeping your own
`progetti/` folder. The pages are served as they are, no build step.

To let `database.php` embed phpMyAdmin, allow same origin framing:

```
$cfg['AllowThirdPartyFraming'] = 'sameorigin';
```

in `vxostfiles/phpmyadmin/config.inc.php`.

## Privacy of the local environment

This build ships **empty**: no projects, no databases, no port configuration.

`.gitignore` uses a whitelist: everything is ignored, and only the files that
belong to the distribution are allowed back in. `.githooks/pre-commit` refuses
any commit that would carry project names, private addresses, dumps or
credentials, enable it with:

```
git config core.hooksPath .githooks
```

## Licence

VXOST and its documentation belong to VXOST and keep their original
licence (GNU GPL v2). VXOST follows the same terms.
