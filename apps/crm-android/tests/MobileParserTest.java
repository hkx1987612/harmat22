package hu.harmat22.crm.mobile;

import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.charset.StandardCharsets;

public final class MobileParserTest {
    private static int count;
    private static void check(boolean value, String message) {
        count++; if (!value) throw new AssertionError(message);
    }
    public static void main(String[] args) throws Exception {
        Path fixtures = Path.of(args[0]);
        for (String role : new String[]{"manager", "staff"}) for (String lang : new String[]{"zh", "hu"}) {
            for (String view : PortalPolicy.VIEWS) {
                String html = Files.readString(fixtures.resolve(role + "-" + lang + "-" + view + ".html"), StandardCharsets.UTF_8);
                PortalPage page;
                try { page = PortalPage.parse(html, view); }
                catch (PortalPage.SchemaChanged changed) {
                    System.err.println(role + " " + lang + " " + view + ": " + org.jsoup.Jsoup.parse(html).select("table thead tr").text());
                    throw changed;
                }
                check(page.user.equals("DEMO"), role + " " + lang + " " + view + " authenticated user");
                if (view.equals("dashboard")) {
                    check(page.days.size() == 7, "seven daily rows");
                    check(page.days.get(0).get(0).equals("2026-11-04"), "latest day first");
                    check(page.days.get(0).get(5).equals(role.equals("manager") ? "4" : "-"), "total and manager-only scope");
                } else {
                    check(!page.records.isEmpty(), role + " " + lang + " " + view + " has records");
                    if (!view.equals("properties")) check(page.records.size() == (role.equals("manager") ? (view.equals("inquiries") ? 4 : 2) : (view.equals("inquiries") ? 2 : 1)), "exact role-filtered record count");
                    for (PortalPage.Record record : page.records) {
                        String all = record.title + " " + record.subtitle + " " + record.status;
                        for (PortalPage.Field field : record.fields) all += " " + field.value;
                        check(!all.contains("demo-only"), "no nonces in parsed data");
                        check(!all.contains("assign_deal") && !all.contains("delete_lead"), "no action payload");
                        if (role.equals("staff")) check(!all.contains("DEMO Client 402") && !all.contains("DEMO Deal 202") && !all.contains("DEMO Inquiry 102"), "no other staff customer");
                    }
                }
                check(page.records.isEmpty() || page.records.get(0).matches("DEMO"), "native search supports names");
            }
        }
        for (String input : new String[]{"http://harmat22.hu/sales/", "https://harmat22.hu.evil.test/sales/", "https://harmat22.hu@evil.test/sales/", "file:///sales/", "javascript:alert(1)", "https://harmat22.hu:444/sales/"}) check(!PortalPolicy.sameOrigin(input), "reject origin " + input);
        check(PortalPolicy.sameOrigin("https://harmat22.hu/sales/"), "allow only official TLS origin");
        check(!PortalPolicy.loginNavigation("https://harmat22.hu/sales/?view=clients"), "login cannot navigate business views");
        check(!PortalPolicy.loginNavigation("https://harmat22.hu/sales/?sales_export=deals_table"), "export excluded");
        check(!PortalPolicy.loginNavigation("https://harmat22.hu/wp-admin/"), "WP admin excluded");
        check(PortalPolicy.loginNavigation("https://harmat22.hu/sales/?wp_lang=zh_CN"), "official login language supported");
        for (String view : PortalPolicy.VIEWS) check(PortalPolicy.readUri(view, "zh").toString().equals("https://harmat22.hu/sales/?view=" + view + "&sales_lang=zh"), "fixed read URL");
        try { PortalPolicy.readUri("payments", "zh"); throw new AssertionError("write/payment view admitted"); } catch (IllegalArgumentException expected) { count++; }
        check(PortalPolicy.authCookies("other=secret; wordpress_logged_in_abc=demo; wordpress_sec_abc=demo2").equals("wordpress_logged_in_abc=demo; wordpress_sec_abc=demo2"), "keep only auth cookies");
        check(PortalPolicy.authCookies("wordpress_logged_in_abc=x\r\nInjected: y").isEmpty(), "header injection rejected");
        check(PortalPolicy.authCookies("wordpress_sec_abc=demo").isEmpty(), "logged-in cookie mandatory");
        check(PortalPolicy.authCookies("wordpress_logged_in_abc=").isEmpty(), "empty session rejected");
        try { PortalPage.parse("<body><form><input name=pwd></form></body>", "dashboard"); throw new AssertionError("guest accepted"); } catch (PortalPage.AuthRequired expected) { count++; }
        try { PortalPage.parse("<body class=harmat-sales-portal-body><div class=harmat-sales-user><span>DEMO</span></div><table></table></body>", "clients"); throw new AssertionError("unknown schema silently empty"); } catch (PortalPage.SchemaChanged expected) { count++; }
        System.out.println("PASS " + count + " parser, role, total and URL/session policy checks");
    }
}
