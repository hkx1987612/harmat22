package hu.harmat22.crm.mobile;

import java.net.URI;
import java.net.URISyntaxException;
import java.util.Set;

/** No caller-supplied URLs, action parameters, exports, or business POSTs. */
public final class PortalPolicy {
    public static final String ORIGIN = "https://harmat22.hu";
    public static final Set<String> VIEWS = java.util.Collections.unmodifiableSet(new java.util.HashSet<>(
        java.util.Arrays.asList("dashboard", "inquiries", "clients", "deals", "properties")));

    private PortalPolicy() {}

    public static URI readUri(String view, String language) {
        if (!VIEWS.contains(view) || !("zh".equals(language) || "hu".equals(language))) {
            throw new IllegalArgumentException("Unsupported read view");
        }
        return URI.create(ORIGIN + "/sales/?view=" + view + "&sales_lang=" + language);
    }

    public static boolean sameOrigin(String value) {
        try {
            URI uri = new URI(value);
            return "https".equalsIgnoreCase(uri.getScheme())
                && "harmat22.hu".equalsIgnoreCase(uri.getHost())
                && (uri.getPort() == -1 || uri.getPort() == 443)
                && uri.getRawUserInfo() == null;
        } catch (URISyntaxException | NullPointerException invalid) { return false; }
    }

    public static boolean loginNavigation(String value) {
        if (!sameOrigin(value)) return false;
        URI uri = URI.create(value);
        if (!("/sales/".equals(uri.getRawPath()) || "/sales".equals(uri.getRawPath()))) return false;
        if (uri.getRawQuery() == null) return true;
        for (String pair : uri.getRawQuery().split("&")) {
            String key = pair.split("=", 2)[0];
            if (!key.matches("wp_lang|sales_lang|auth_error|lost_password|reset_notice|password_error|password_changed|force_password_change")) return false;
        }
        return true;
    }

    public static String authCookies(String header) {
        if (header == null || header.length() > 16384 || header.indexOf('\r') >= 0 || header.indexOf('\n') >= 0) return "";
        StringBuilder clean = new StringBuilder();
        boolean loggedIn = false;
        for (String part : header.split(";")) {
            String cookie = part.trim();
            int separator = cookie.indexOf('=');
            if (separator <= 0 || separator == cookie.length() - 1) continue;
            String name = cookie.substring(0, separator);
            if (!name.matches("wordpress_logged_in_[A-Za-z0-9]+|wordpress_sec_[A-Za-z0-9]+|wordpress_[A-Za-z0-9]+")) continue;
            if (name.startsWith("wordpress_logged_in_")) loggedIn = true;
            if (clean.length() > 0) clean.append("; ");
            clean.append(cookie);
        }
        return loggedIn ? clean.toString() : "";
    }
}
