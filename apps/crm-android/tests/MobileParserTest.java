package hu.harmat22.crm.mobile;

import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.charset.StandardCharsets;
import org.jsoup.Jsoup;
import org.jsoup.nodes.Document;
import org.jsoup.nodes.Element;
import org.jsoup.select.Elements;

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
                    schemaCases(html, view, lang);
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

    private static String tableSelector(String view) {
        return switch (view) {
            case "inquiries" -> ".harmat-sales-inquiry-table";
            case "clients" -> ".harmat-sales-client-table";
            case "deals" -> ".harmat-sales-deal-table";
            default -> ".harmat-sales-property-table";
        };
    }

    private static void rejectSchema(Document document, String view, String message) throws Exception {
        try {
            PortalPage.parse(document.outerHtml(), view);
            throw new AssertionError(message);
        } catch (PortalPage.SchemaChanged expected) { count++; }
    }

    private static void headerMutations(Document original, String view) throws Exception {
        String selector = tableSelector(view);
        int columns = original.selectFirst(selector).select("> thead > tr > th").size();
        for (int i = 0; i < columns; i++) {
            Document renamed = original.clone();
            Element header = renamed.selectFirst(selector).select("> thead > tr > th").get(i);
            header.text(header.text() + " changed");
            rejectSchema(renamed, view, view + " renamed header " + i + " admitted");
        }
        for (int i = 0; i < columns - 1; i++) {
            Document reordered = original.clone();
            Element table = reordered.selectFirst(selector);
            Elements headers = table.select("> thead > tr > th");
            headers.get(i).before(headers.get(i + 1));
            for (Element row : table.select("> tbody > tr")) row.child(i).before(row.child(i + 1));
            rejectSchema(reordered, view, view + " reordered column " + i + " admitted");
        }
    }

    private static void schemaCases(String html, String view, String language) throws Exception {
        Document original = Jsoup.parse(html);
        String selector = tableSelector(view);
        headerMutations(original, view);

        Document missing = original.clone();
        missing.selectFirst(selector).remove();
        rejectSchema(missing, view, view + " missing table admitted");
        missing.body().appendElement("div").addClass("harmat-sales-empty").text("Unrelated widget empty");
        rejectSchema(missing, view, view + " unrelated empty widget admitted");

        Document renamedTable = original.clone();
        renamedTable.selectFirst(selector).removeClass(selector.substring(1)).addClass("changed-table");
        renamedTable.body().appendElement("div").addClass("harmat-sales-empty").text("Unrelated widget empty");
        rejectSchema(renamedTable, view, view + " renamed table with unrelated empty widget admitted");

        Document noLanguage = original.clone();
        noLanguage.body().removeAttr("data-sales-lang");
        rejectSchema(noLanguage, view, view + " unknown language admitted");

        Document emptyTable = original.clone();
        Element tbody = emptyTable.selectFirst(selector).selectFirst("> tbody");
        tbody.empty();
        rejectSchema(emptyTable, view, view + " unrecognized empty table admitted");
        tbody.appendElement("tr").appendElement("td").attr("colspan", "99").text("Unknown placeholder");
        rejectSchema(emptyTable, view, view + " arbitrary colspan placeholder admitted");

        for (String message : emptyMessages(view, language.equals("hu"))) {
            Document valid = original.clone();
            Element panel = valid.selectFirst(selector).closest("section.harmat-sales-panel");
            Element heading = panel.selectFirst("> .harmat-sales-panel-head").clone();
            panel.empty().appendChild(heading);
            panel.appendElement("div").addClass("harmat-sales-empty").text(message);
            PortalPage page = PortalPage.parse(valid.outerHtml(), view);
            check(page.records.isEmpty() && page.user.equals("DEMO"), language + " " + view + " authorized empty state");

            Document wrongText = valid.clone();
            wrongText.selectFirst(selectorForEmptyPanel()).text("Unrelated widget empty");
            rejectSchema(wrongText, view, view + " unrelated empty text admitted");

            Document wrongPanel = valid.clone();
            Element empty = wrongPanel.selectFirst(selectorForEmptyPanel());
            empty.closest("section.harmat-sales-panel").selectFirst("> .harmat-sales-panel-head > div > h2").text("Unrelated panel");
            rejectSchema(wrongPanel, view, view + " known empty text in wrong panel admitted");

            Document outsidePanel = valid.clone();
            Element misplaced = outsidePanel.selectFirst(selectorForEmptyPanel());
            misplaced.remove(); outsidePanel.body().appendChild(misplaced);
            rejectSchema(outsidePanel, view, view + " known empty text outside its panel admitted");
        }
        if (view.equals("deals")) commissionCases(original, language.equals("hu"));
    }

    private static String selectorForEmptyPanel() { return "section.harmat-sales-panel > div.harmat-sales-empty"; }

    private static String[] emptyMessages(String view, boolean hu) {
        return switch (view) {
            case "inquiries" -> hu ? new String[]{"A jelenlegi sz\u0171r\u00e9s mellett nincs \u00e9rdekl\u0151d\u00e9si rekord."}
                : new String[]{"\u5f53\u524d\u7b5b\u9009\u6761\u4ef6\u4e0b\u6ca1\u6709\u627e\u5230\u8be2\u4ef7\u8bb0\u5f55\u3002"};
            case "clients" -> hu ? new String[]{"M\u00e9g nincs \u00fcgyf\u00e9lakta."}
                : new String[]{"\u6682\u65e0\u5ba2\u6237\u8bb0\u5f55\u3002"};
            case "deals" -> hu ? new String[]{
                "M\u00e9g nincs \u00e9rt\u00e9kes\u00edt\u00e9si \u00fcgy. Az els\u0151 \u00fcgy weboldali \u00e9rdekl\u0151d\u00e9sb\u0151l vagy \u00fcgyf\u00e9lakt\u00e1b\u00f3l hozhat\u00f3 l\u00e9tre.",
                "A jelenlegi sz\u0171r\u00e9s mellett nincs \u00e9rt\u00e9kes\u00edt\u00e9si \u00fcgy."
            } : new String[]{
                "\u6682\u65e0\u9500\u552e\u8ddf\u5355\u3002\u53ef\u4ee5\u4ece\u7f51\u7ad9\u8be2\u4ef7\u6216\u5ba2\u6237\u8ddf\u8fdb\u751f\u6210\u7b2c\u4e00\u6761\u8ddf\u5355\u3002",
                "\u5f53\u524d\u7b5b\u9009\u6761\u4ef6\u4e0b\u6ca1\u6709\u9500\u552e\u8ddf\u5355\u3002"
            };
            default -> hu ? new String[]{"Nincs lak\u00e1sadat.", "A jelenlegi sz\u0171r\u00e9sre nincs tal\u00e1lat."}
                : new String[]{"\u6682\u65e0\u623f\u6e90\u3002", "\u5f53\u524d\u7b5b\u9009\u6761\u4ef6\u4e0b\u6ca1\u6709\u623f\u6e90\u3002"};
        };
    }

    private static void commissionCases(Document original, boolean hu) throws Exception {
        Document without = original.clone();
        String selector = tableSelector("deals");
        Element table = without.selectFirst(selector);
        if (table.select("> thead > tr > th").size() == 12) {
            table.select("> thead > tr > th").get(4).remove();
            for (Element row : table.select("> tbody > tr")) row.child(4).remove();
        }
        PortalPage baseline = PortalPage.parse(without.outerHtml(), "deals");
        check(!baseline.records.isEmpty(), "deals without commission accepted");

        Document with = without.clone();
        table = with.selectFirst(selector);
        String label = hu ? "Jutal\u00e9k" : "\u4f63\u91d1";
        table.select("> thead > tr > th").get(4).before(new Element("th").text(label));
        for (Element row : table.select("> tbody > tr")) row.child(4).before(new Element("td").text("123 HUF"));
        PortalPage expanded = PortalPage.parse(with.outerHtml(), "deals");
        check(expanded.records.size() == baseline.records.size(), "deals with commission preserve rows");
        for (int i = 0; i < baseline.records.size(); i++) {
            PortalPage.Record before = baseline.records.get(i), after = expanded.records.get(i);
            check(before.title.equals(after.title) && before.status.equals(after.status) && before.subtitle.equals(after.subtitle), "commission does not shift core deal fields");
            check(after.fields.size() == before.fields.size() + 1, "commission adds exactly one field");
            check(after.fields.stream().anyMatch(field -> field.label.equals(label) && field.value.equals("123 HUF")), "commission retains its semantic label/value");
            for (PortalPage.Field field : before.fields) {
                check(after.fields.stream().anyMatch(candidate -> candidate.label.equals(field.label) && candidate.value.equals(field.value)), "commission preserves " + field.label);
            }
        }
        headerMutations(with, "deals");
    }
}
