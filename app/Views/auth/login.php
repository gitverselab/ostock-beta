<div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Sign In</h1>
            <p class="mt-1 text-sm text-slate-500">
                Login to access your inventory dashboard.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <?= htmlspecialchars((string) $error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login" class="space-y-4">
            <div>
                <label for="username" class="mb-1 block text-sm font-medium text-slate-700">
                    Username
                </label>
                <input
                    id="username"
                    name="username"
                    type="text"
                    required
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-slate-700">
                    Password
                </label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
            >
                Sign In
            </button>
        </form>

        <div class="mt-6 rounded-xl bg-slate-50 p-4 text-xs text-slate-500">
            Temporary demo login:
            <span class="font-semibold text-slate-700">admin / admin123</span>
        </div>
    </div>
</div>