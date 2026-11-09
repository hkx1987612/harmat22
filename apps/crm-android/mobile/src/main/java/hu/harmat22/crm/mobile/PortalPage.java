package hu.harmat22.crm.mobile;

import org.jsoup.Jsoup;
import org.jsoup.nodes.Document;
import org.jsoup.nodes.Element;
import org.jsoup.nodes.TextNode;
import org.jsoup.select.Elements;

import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.Locale;

/** Parses only already-authorized server-rendered data; never follows row links. */
public final class PortalPage {
    public static final class AuthRequired extends Exception {}
    public static final class SchemaChanged extends Exception {}
    public static final class Field {
        public final String label, value;
        Field(String label, String value) { this.label = label; this.value = value; }
    }
    public static final class Record {
        public final String title, subtitle, status;
        public final List<Field> fields;
        Record(String title, String subtitle, String status, List<Field> fields) {
            this.title = title; this.subtitle = subtitle; this.status = status;
            this.fields = java.util.Collections.unmodifiableList(new ArrayList<>(fields));
        }
        public boolean matches(String query) {
            String needle = query.trim().toLowerCase(Locale.ROOT);
            if (needle.isEmpty()) return true;
            if ((title + " " + subtitle + " " + status).toLowerCase(Locale.ROOT).contains(needle)) return true;
            for (Field field : fields) if (field.value.toLowerCase(Locale.ROOT).contains(needle)) return true;
            return false;
        }
    }
    public final String view, user, role;
    public final List<Field> metrics = new ArrayList<>();
    public final List<List<String>> days = new ArrayList<>();
    public final List<Record> records = new ArrayList<>();
    public final long fetchedAt = System.currentTimeMillis();

    private PortalPage(String view, String user, String role) { this.view = view; this.user = user; this.role = role; }

    public static PortalPage parse(String html, String view) throws AuthRequired, SchemaChanged {
        if (!PortalPolicy.VIEWS.contains(view)) throw new SchemaChanged();
        Document document = Jsoup.parse(html);
        Element body = document.selectFirst("body.harmat-sales-portal-body");
        if (body == null || body.selectFirst(".harmat-sales-user") == null) throw new AuthRequired();
        PortalPage page = new PortalPage(view, text(body.selectFirst(".harmat-sales-user > span")),
            text(body.selectFirst(".harmat-sales-role-badge")));
        if (view.equals("dashboard")) {
            for (Element metric : body.select(".harmat-sales-traffic-strip > span")) {
                page.metrics.add(new Field(text(metric.selectFirst("small")), text(metric.selectFirst("strong"))));
            }
            for (Element row : body.select(".harmat-sales-traffic-day-table tbody > tr")) {
                Elements cells = directCells(row);
                if (cells.size() != 6) throw new SchemaChanged();
                List<String> day = new ArrayList<>();
                day.add(text(cells.get(0).selectFirst("small")));
                for (int i = 1; i < cells.size(); i++) day.add(cellText(cells.get(i)));
                page.days.add(java.util.Collections.unmodifiableList(day));
            }
            page.days.sort(Comparator.comparing((List<String> day) -> day.get(0)).reversed());
            for (Element item : body.select(".harmat-sales-dashboard-item")) {
                page.records.add(new Record(text(item.selectFirst("header strong")), text(item.selectFirst("p")),
                    text(item.selectFirst("header span")), java.util.Collections.singletonList(new Field("", text(item.selectFirst("small"))))));
            }
            if (body.selectFirst(".harmat-sales-overview-lists") == null) throw new SchemaChanged();
            return page;
        }
        String tableClass = switch (view) {
            case "inquiries" -> ".harmat-sales-inquiry-table";
            case "clients" -> ".harmat-sales-client-table";
            case "deals" -> ".harmat-sales-deal-table";
            default -> ".harmat-sales-property-table";
        };
        String language = body.attr("data-sales-lang");
        if (!language.equals("hu") && !language.equals("zh")) throw new SchemaChanged();
        boolean hu = language.equals("hu");
        Element table = body.selectFirst(tableClass);
        if (table == null) {
            if (knownEmpty(body, view, hu)) return page;
            throw new SchemaChanged();
        }
        Elements headers = table.select("> thead > tr > th");
        validateHeaders(headers, view, hu);
        Elements rows = table.select("> tbody > tr");
        // The current renderer returns a named empty panel before emitting a table.
        if (rows.isEmpty()) throw new SchemaChanged();
        for (Element row : rows) {
            Elements cells = directCells(row);
            if (cells.size() != headers.size()) throw new SchemaChanged();
            int titleColumn = view.equals("inquiries") ? 2 : 0;
            int propertyColumn = view.equals("inquiries") ? 3 : view.equals("properties") ? 5 : 1;
            int statusColumn = view.equals("inquiries") ? 5 : view.equals("properties") ? 1 : view.equals("clients") ? 1 : 2;
            String title = text(cells.get(titleColumn).selectFirst("strong"));
            if (title.isEmpty()) title = cellText(cells.get(titleColumn));
            List<Field> fields = new ArrayList<>();
            for (int i = 0; i < cells.size(); i++) {
                if (i == cells.size() - 1 || (view.equals("properties") && i == 10)) continue;
                String value = cellText(cells.get(i));
                if (!value.isEmpty()) fields.add(new Field(headers.get(i).text(), value));
            }
            Element pill = cells.get(statusColumn).selectFirst(".harmat-sales-contact-progress, .harmat-sales-pill, select");
            String status = pill == null ? cellText(cells.get(statusColumn)) : cellText(pill);
            page.records.add(new Record(title, cellText(cells.get(propertyColumn)), status, fields));
        }
        // The website sorts enquiries with JavaScript. Apply its ISO-date order natively.
        if (view.equals("inquiries")) page.records.sort(Comparator.comparing((Record record) -> record.fields.get(1).value).reversed());
        return page;
    }

    private static void validateHeaders(Elements headers, String view, boolean hu) throws SchemaChanged {
        String[] expected = switch (view) {
            case "inquiries" -> hu ? new String[]{
                "Forr\u00e1s", "Bek\u00fcld\u00e9s / r\u00f6gz\u00edt\u00e9s ideje", "\u00dcgyf\u00e9l", "Lak\u00e1s", "El\u00e9rhet\u0151s\u00e9g",
                "\u00dcgyf\u00e9l\u00e1llapot", "K\u00f6vet\u0151 \u00e9rt\u00e9kes\u00edt\u0151", "\u00dczenet / bel\u00e9p\u00e9s", "M\u0171velet"
            } : new String[]{
                "\u6765\u6e90", "\u63d0\u4ea4/\u767b\u8bb0\u65f6\u95f4", "\u5ba2\u6237", "\u623f\u53f7", "\u8054\u7cfb\u65b9\u5f0f",
                "\u5ba2\u6237\u8fdb\u5c55", "\u8ddf\u8fdb\u9500\u552e", "\u7559\u8a00/\u5165\u53e3", "\u64cd\u4f5c"
            };
            case "clients" -> hu ? new String[]{
                "\u00dcgyf\u00e9l / kapcsolat", "Lak\u00e1s / st\u00e1tusz", "K\u00f6vet\u00e9s", "Felel\u0151s \u00e9rt\u00e9kes\u00edt\u0151",
                "K\u00f6zvet\u00edt\u0151", "Megjegyz\u00e9s", "M\u0171veletek"
            } : new String[]{
                "\u5ba2\u6237 / \u8054\u7cfb", "\u623f\u6e90 / \u72b6\u6001", "\u8ddf\u8fdb\u65f6\u95f4", "\u9500\u552e\u8d1f\u8d23\u4eba",
                "\u7ecf\u7eaa\u4eba", "\u5907\u6ce8", "\u64cd\u4f5c"
            };
            case "deals" -> hu ? new String[]{
                "\u00dcgyf\u00e9l", "Lak\u00e1s", "F\u00e1zis", "\u00d6sszeg / foglal\u00f3", "Jutal\u00e9k", "Fizet\u00e9s / szerz\u0151d\u00e9s",
                "K\u00f6vetkez\u0151 l\u00e9p\u00e9s", "K\u00f6vet\u0151 \u00e9rt\u00e9kes\u00edt\u0151", "K\u00f6zvet\u00edt\u0151", "Forr\u00e1s", "Friss\u00edt\u00e9s", "M\u0171velet"
            } : new String[]{
                "\u5ba2\u6237", "\u623f\u6e90", "\u9636\u6bb5", "\u91d1\u989d/\u5b9a\u91d1", "\u4f63\u91d1", "\u4ed8\u6b3e/\u5408\u540c",
                "\u4e0b\u4e00\u6b65", "\u8ddf\u8fdb\u9500\u552e", "\u7ecf\u7eaa\u4eba", "\u6765\u6e90", "\u66f4\u65b0", "\u64cd\u4f5c"
            };
            default -> hu ? new String[]{
                "Lak\u00e1s", "St\u00e1tusz", "\u00c9p\u00fclet", "Emelet", "Szob\u00e1k", "\u00c9rt\u00e9kes\u00edt\u00e9si ter\u00fclet",
                "Terasz / kert", "Teljes \u00e1r HUF", "Nyilv\u00e1nos \u00e1r", "Megjegyz\u00e9s", "Link", "M\u0171velet"
            } : new String[]{
                "\u623f\u53f7", "\u72b6\u6001", "\u697c\u680b", "\u697c\u5c42", "\u623f\u95f4", "\u9500\u552e\u9762\u79ef",
                "\u9732\u53f0/\u82b1\u56ed", "\u603b\u4ef7 HUF", "\u524d\u7aef\u4ef7\u683c", "\u5907\u6ce8", "\u94fe\u63a5", "\u64cd\u4f5c"
            };
        };
        boolean withoutCommission = view.equals("deals") && headers.size() == expected.length - 1;
        if (headers.size() != expected.length && !withoutCommission) throw new SchemaChanged();
        for (int i = 0, column = 0; i < expected.length; i++) {
            if (withoutCommission && i == 4) continue;
            if (!expected[i].equals(text(headers.get(column++)))) throw new SchemaChanged();
        }
    }

    private static boolean knownEmpty(Element body, String view, boolean hu) {
        // Pin both the list heading and its direct empty-state text, not another widget's state.
        String[] expected = switch (view) {
            case "inquiries" -> hu ? new String[]{
                "\u00c9rdekl\u0151d\u00e9sek \u00f6sszes\u00edt\u00e9se", "A jelenlegi sz\u0171r\u00e9s mellett nincs \u00e9rdekl\u0151d\u00e9si rekord."
            } : new String[]{"\u8be2\u4ef7\u6c47\u603b", "\u5f53\u524d\u7b5b\u9009\u6761\u4ef6\u4e0b\u6ca1\u6709\u627e\u5230\u8be2\u4ef7\u8bb0\u5f55\u3002"};
            case "clients" -> hu ? new String[]{"\u00dcgyf\u00e9lakt\u00e1k", "M\u00e9g nincs \u00fcgyf\u00e9lakta."}
                : new String[]{"\u5ba2\u6237\u6863\u6848\u5217\u8868", "\u6682\u65e0\u5ba2\u6237\u8bb0\u5f55\u3002"};
            case "deals" -> hu ? new String[]{
                "\u00c9rt\u00e9kes\u00edt\u00e9si \u00fcgyek", "M\u00e9g nincs \u00e9rt\u00e9kes\u00edt\u00e9si \u00fcgy. Az els\u0151 \u00fcgy weboldali \u00e9rdekl\u0151d\u00e9sb\u0151l vagy \u00fcgyf\u00e9lakt\u00e1b\u00f3l hozhat\u00f3 l\u00e9tre.",
                "A jelenlegi sz\u0171r\u00e9s mellett nincs \u00e9rt\u00e9kes\u00edt\u00e9si \u00fcgy."
            } : new String[]{
                "\u9500\u552e\u8ddf\u5355\u5217\u8868", "\u6682\u65e0\u9500\u552e\u8ddf\u5355\u3002\u53ef\u4ee5\u4ece\u7f51\u7ad9\u8be2\u4ef7\u6216\u5ba2\u6237\u8ddf\u8fdb\u751f\u6210\u7b2c\u4e00\u6761\u8ddf\u5355\u3002",
                "\u5f53\u524d\u7b5b\u9009\u6761\u4ef6\u4e0b\u6ca1\u6709\u9500\u552e\u8ddf\u5355\u3002"
            };
            default -> hu ? new String[]{"Lak\u00e1sk\u00e9szlet", "Nincs lak\u00e1sadat.", "A jelenlegi sz\u0171r\u00e9sre nincs tal\u00e1lat."}
                : new String[]{"\u623f\u6e90\u5e93\u5b58", "\u6682\u65e0\u623f\u6e90\u3002", "\u5f53\u524d\u7b5b\u9009\u6761\u4ef6\u4e0b\u6ca1\u6709\u623f\u6e90\u3002"};
        };
        Element panel = null;
        for (Element candidate : body.select("section.harmat-sales-panel")) {
            if (!expected[0].equals(text(candidate.selectFirst("> .harmat-sales-panel-head > div > h2")))) continue;
            if (panel != null) return false;
            panel = candidate;
        }
        if (panel == null || panel.selectFirst("table") != null) return false;
        Elements empty = panel.select("> div.harmat-sales-empty");
        if (empty.size() != 1) return false;
        for (int i = 1; i < expected.length; i++) if (expected[i].equals(text(empty.first()))) return true;
        return false;
    }

    private static String text(Element element) { return element == null ? "" : element.text().trim(); }

    private static Elements directCells(Element row) {
        Elements cells = new Elements();
        for (Element child : row.children()) if (child.normalName().equals("td")) cells.add(child);
        return cells;
    }

    private static String cellText(Element source) {
        if (source.normalName().equals("select")) {
            Element selected = source.selectFirst("option[selected]");
            return text(selected == null ? source.selectFirst("option") : selected);
        }
        Element copy = source.clone();
        copy.select("script, style, button, input[type=hidden], summary, .harmat-sales-row-more").remove();
        for (Element select : copy.select("select")) {
            Element selected = select.selectFirst("option[selected]");
            if (selected == null) selected = select.selectFirst("option");
            select.replaceWith(new TextNode(text(selected) + " "));
        }
        for (Element input : copy.select("input, textarea")) {
            String name = input.attr("name");
            input.replaceWith(new TextNode((name.equals("property_price") || name.equals("sales_note"))
                ? (input.tagName().equals("textarea") ? input.text() : input.attr("value")) : ""));
        }
        for (Element part : copy.select("small, strong, span, p, br")) part.appendText(" ");
        return copy.text().trim();
    }
}
