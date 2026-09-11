# Third-party licences

VXOST is a distribution: most of what it installs is software written by other
people, shipped unmodified and under its own licence. Nothing here is
superseded by the GNU GPL v2 that covers VXOST's own code — the dashboard, the
Dock app and the status bar app.

The full text of every licence below ships inside the package, in
`vxostfiles/licenses/`.

## Core components

| Component | Licence |
| --- | --- |
| Apache HTTP Server | Apache License 2.0 |
| MariaDB | GNU GPL v2 |
| PHP | PHP License v3.01 |
| Perl | Artistic License / GNU GPL v1 or later |
| ProFTPD | GNU GPL v2 |
| phpMyAdmin | GNU GPL v2 |
| OpenSSL 1.1.1 | OpenSSL License and SSLeay License (dual) |
| cURL | curl licence (MIT/X derivative) |

OpenSSL changed licence with 3.0, to the Apache License 2.0. The stack ships
1.1.1, which predates that change: `vxostfiles/licenses/openssl.txt` carries
both the OpenSSL and the SSLeay texts, and both apply. If the stack ever moves
to OpenSSL 3, this row changes with it.

A further 50 or so libraries — zlib, libpng, freetype, PCRE, ICU, expat and the
rest of the stack — each keep their own licence file in `vxostfiles/licenses/`.

## Trademarks

Apache, Apache HTTP Server and the feather logo are trademarks of the Apache
Software Foundation. MariaDB is a trademark of MariaDB Corporation Ab. Their use
here is descriptive: it names the software VXOST installs, and implies no
endorsement by, or affiliation with, those projects.

macOS and Apple Silicon are trademarks of Apple Inc.

## Reporting a problem

If something in this list is wrong, incomplete or out of date, open an issue: a
licensing mistake is a bug like any other, and it is fixed the same way.
