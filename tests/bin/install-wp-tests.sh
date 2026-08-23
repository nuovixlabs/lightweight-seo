#!/usr/bin/env bash
set -euo pipefail

wp_version="${1:-latest}"
wp_tests_dir="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
wp_core_dir="${WP_CORE_DIR:-/tmp/wordpress}"

if [[ "${wp_version}" == "latest" ]]; then
  archive_url="https://wordpress.org/latest.tar.gz"
else
  archive_url="https://wordpress.org/wordpress-${wp_version}.tar.gz"
fi

if [[ -e "${wp_core_dir}" || -e "${wp_tests_dir}" ]]; then
  echo "WordPress test paths must not already exist: ${wp_core_dir}, ${wp_tests_dir}" >&2
  exit 1
fi

mkdir -p "${wp_core_dir}" "${wp_tests_dir}"
curl -fsSL "${archive_url}" | tar -xz --strip-components=1 -C "${wp_core_dir}"

develop_tag="${wp_version}"

if [[ "${wp_version}" == "latest" ]]; then
  develop_tag="$(sed -n "s/^\\\$wp_version = '\([^']*\)';/\1/p" "${wp_core_dir}/wp-includes/version.php")"
fi

if [[ -z "${develop_tag}" ]]; then
  echo "Could not determine the matching WordPress test-library version." >&2
  exit 1
fi

if [[ "${develop_tag}" =~ ^[0-9]+\.[0-9]+$ ]]; then
  develop_tag="${develop_tag}.0"
fi

develop_archive_url="https://github.com/WordPress/wordpress-develop/archive/refs/tags/${develop_tag}.tar.gz"
develop_archive_root="wordpress-develop-${develop_tag}"

develop_archive="$(mktemp /tmp/wordpress-develop.XXXXXX)"
trap 'rm -f "${develop_archive}"' EXIT
curl -fsSL "${develop_archive_url}" -o "${develop_archive}"
tar -xzf "${develop_archive}" --strip-components=3 -C "${wp_tests_dir}" "${develop_archive_root}/tests/phpunit"
tar -xzf "${develop_archive}" --strip-components=1 -C "${wp_tests_dir}" "${develop_archive_root}/wp-tests-config-sample.php"

sed \
  -e "s/youremptytestdbnamehere/${WP_TESTS_DB_NAME:-wordpress_test}/" \
  -e "s/yourusernamehere/${WP_TESTS_DB_USER:-root}/" \
  -e "s/yourpasswordhere/${WP_TESTS_DB_PASSWORD-root}/" \
  -e "s|localhost|${WP_TESTS_DB_HOST:-127.0.0.1}|" \
  -e "s|dirname( __FILE__ ) . '/src/'|'${wp_core_dir}/'|" \
  "${wp_tests_dir}/wp-tests-config-sample.php" > "${wp_tests_dir}/wp-tests-config.php"
