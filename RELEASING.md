# Releasing

1. Update the version in three places: the `Version:` header and `VAARTIX_PLUGIN_VERSION` in
   `vaartix.php`, and `Stable tag:` in `readme.txt`. Add a changelog entry in `readme.txt`.
2. Merge to `main` and wait for CI to pass.
3. On GitHub, create a release with the tag `vX.Y.Z` (for example `v0.1.1`) and publish it.
4. The Release workflow checks the tag matches the version, then attaches `vaartix.zip` and
   `vaartix.zip.sha256` to the release.

The website links to
`https://github.com/vaartix-mail/vaartix-wordpress/releases/latest/download/vaartix.zip`,
which always serves the ZIP from the newest release.
