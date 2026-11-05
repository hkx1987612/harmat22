package hu.harmat22.crm.preview;

import java.util.HashSet;
import java.util.List;

public class DemoDataTest {
    private static int checks;
    private static void check(boolean ok, String name) {
        checks++;
        if (!ok) throw new AssertionError(name);
    }
    public static void main(String[] args) {
        List<DemoData.Client> all = DemoData.select("", null, null);
        check(all.size() == 6, "all demo clients");
        check(new HashSet<>(all.stream().map(c -> c.id).toList()).size() == 6, "unique identifiers");
        for (int i = 1; i < all.size(); i++) check(all.get(i - 1).updated().compareTo(all.get(i).updated()) >= 0, "latest first");
        check(DemoData.select("", DemoData.Source.WALK_IN, null).size() == 3, "walk-in filter");
        check(DemoData.select("", DemoData.Source.WEBSITE, null).size() == 3, "website filter");
        check(DemoData.select("", null, DemoData.Status.NEW).size() == 2, "new filter");
        check(DemoData.select("", DemoData.Source.WALK_IN, DemoData.Status.NEW).isEmpty(), "combined filter");
        check(DemoData.select("  d-01 ", null, null).size() == 2, "trimmed unit search");
        check(DemoData.select("示例客户 02", null, null).size() == 1, "Chinese search");
        check(DemoData.select("UGYFEL 03", null, null).size() == 1, "Hungarian accent-insensitive search");
        check(DemoData.select("nonexistent", null, null).isEmpty(), "empty result");
        int[] totals = DemoData.totals();
        check(totals[0] == 2038, "views total");
        check(totals[1] == 17, "website total");
        check(totals[2] == 10, "walk-in total");
        check(totals[3] == 27, "all enquiries total");
        for (DemoData.Day day : DemoData.DAYS) check(day.enquiries() == day.website + day.walkIn, "row total");
        for (DemoData.Client client : all) check(client.email().endsWith("@example.invalid"), "synthetic contacts only");
        System.out.println("PASS " + checks + " demo data checks");
    }
}
