package hu.harmat22.crm.mobile;

public final class CallbackOwnerTest {
    private static int checks;
    private static void check(boolean ok, String label) {
        checks++; if (!ok) throw new AssertionError(label);
    }
    public static void main(String[] args) {
        for (String label : new String[]{"biometric", "login"}) {
            CallbackOwner<Object> owner = new CallbackOwner<>();
            Object a = new Object(), b = new Object();
            long first = owner.begin(a);
            check(owner.matches(first, a), label + " first owns callback");
            owner.invalidate();
            long second = owner.begin(b);
            check(!owner.finish(first, a), label + " old cancellation cannot finish new operation");
            check(owner.matches(second, b), label + " new operation remains current");
            check(!owner.finish(second, a), label + " wrong view/signal rejected");
            check(owner.matches(second, b), label + " wrong owner did not clear new operation");
            check(owner.finish(second, b), label + " current operation completes");
            check(!owner.finish(second, b), label + " duplicate success/error ignored");
            long reused = owner.begin(b);
            check(!owner.matches(second, b), label + " owner reuse does not admit old callback");
            check(owner.matches(reused, b), label + " reused owner has new epoch");
            owner.invalidate();
            check(!owner.matches(reused, b), label + " lock invalidates pending callback");
            check(!owner.finish(reused, b), label + " post-lock callback ignored");
        }
        try {new CallbackOwner<>().begin(null); throw new AssertionError("null owner accepted");}
        catch (IllegalArgumentException expected) {checks++;}
        System.out.println("PASS " + checks + " callback-owner sequence checks");
    }
}
