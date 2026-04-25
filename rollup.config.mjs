/**
 * Rollup configuration for the Contacts admin assets.
 *
 * Output filenames are static (no content hashes) because they are
 * referenced by name in PHP via Requirements::css() / Requirements::javascript()
 * and must not change between builds.
 *
 * CSS is compiled separately via the `build:css` npm script using the Sass CLI
 * to guarantee a stable output filename (bundle.css).
 */
export default [
    {
        input: 'client/admin/src/index.js',
        output: {
            file: 'client/admin/dist/bundle.js',
            format: 'iife',
            // No hashing — filename must be stable so it can be referenced in PHP
        },
    }, {
        input: 'client/admin/src/address-region-dropdown.js',
        output: {
            file: 'client/admin/dist/address-region-dropdown.js',
            format: 'iife',
            // No hashing — filename must be stable so it can be referenced in PHP
        },
    },
];

