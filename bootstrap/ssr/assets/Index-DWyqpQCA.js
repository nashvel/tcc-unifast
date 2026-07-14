import { defineComponent, ref, computed, resolveComponent, unref, withCtx, createTextVNode, createVNode, openBlock, createBlock, toDisplayString, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrRenderClass, ssrInterpolate } from "vue/server-renderer";
import { useRoute } from "vue-router";
import { IconDownload, IconPlus, IconSearch, IconKey, IconArrowLeft, IconCheck, IconX } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./AppDialog-CSs1wZpw.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const route = useRoute();
    const search = ref("");
    const roleFilter = ref("All roles");
    const statusFilter = ref("All statuses");
    const userDialog = ref(false);
    const accountDialog = ref(false);
    const accountAction = ref("");
    const accountName = ref("");
    const isMatrix = computed(
      () => route.params.section === "permissions" || route.path.endsWith("/users/permissions")
    );
    const users = [
      [
        "sysadmin",
        "System Administrator",
        "admin@unifast.gov.ph",
        "Admin",
        true,
        true,
        "Jul 11, 2026, 7:41 PM"
      ],
      [
        "office.head",
        "Office Head",
        "head@unifast.gov.ph",
        "Head",
        true,
        true,
        "Jul 11, 2026, 5:12 PM"
      ],
      [
        "unifast.staff",
        "UniFAST Staff",
        "staff@unifast.gov.ph",
        "Staff",
        false,
        true,
        "Jul 11, 2026, 4:48 PM"
      ],
      [
        "reviewer.01",
        "Document Reviewer",
        "reviewer@unifast.gov.ph",
        "Staff",
        false,
        false,
        "Jul 8, 2026, 9:16 AM"
      ]
    ];
    const filtered = computed(
      () => users.filter((user) => {
        const matchesSearch = !search.value || `${user[0]} ${user[1]} ${user[2]}`.toLowerCase().includes(search.value.toLowerCase());
        const matchesRole = roleFilter.value === "All roles" || user[3] === roleFilter.value;
        const matchesStatus = statusFilter.value === "All statuses" || (statusFilter.value === "Active" ? user[5] : !user[5]);
        return matchesSearch && matchesRole && matchesStatus;
      })
    );
    const permissionGroups = [
      { module: "Operations", permissions: ["view masterlist", "manage batches", "manage grantees"] },
      {
        module: "Validation",
        permissions: ["validate documents", "review academics", "run eligibility"]
      },
      {
        module: "Communication",
        permissions: ["publish announcements", "generate reports", "manage support"]
      },
      {
        module: "Administration",
        permissions: ["view audit trail", "manage users", "change settings"]
      }
    ];
    const allowed = (role, permission) => role === "Head" || (role === "Admin" ? [
      "publish announcements",
      "generate reports",
      "manage support",
      "view audit trail",
      "manage users",
      "change settings"
    ].includes(permission) : !["manage users", "change settings"].includes(permission));
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      if (!isMatrix.value) {
        _push(`<div${ssrRenderAttrs(_attrs)}><header class="mb-4 flex flex-wrap items-start justify-between gap-3"><div><h1 class="text-2xl font-semibold tracking-tight">Users &amp; Access</h1><p class="mt-1 text-sm text-text-muted">Manage staff accounts, roles, and permissions.</p></div><div class="flex gap-2"><button class="inline-flex h-9 items-center gap-1.5 rounded-md border bg-surface px-3 text-xs">`);
        _push(ssrRenderComponent(unref(IconDownload), { size: 14 }, null, _parent));
        _push(`Export</button>`);
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/app/users/permissions",
          class: "inline-flex h-9 items-center rounded-md border bg-surface px-3 text-xs"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`Permission matrix`);
            } else {
              return [
                createTextVNode("Permission matrix")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`<button class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white">`);
        _push(ssrRenderComponent(unref(IconPlus), { size: 14 }, null, _parent));
        _push(`New user </button></div></header><section class="mb-4 grid grid-cols-1 gap-2 rounded-lg border bg-surface p-3 md:grid-cols-4"><div class="relative md:col-span-2">`);
        _push(ssrRenderComponent(unref(IconSearch), {
          size: 14,
          class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
        }, null, _parent));
        _push(`<input${ssrRenderAttr("value", search.value)} placeholder="Search by name, username, or email" class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"></div><select class="h-9 rounded-md border bg-surface px-3 text-xs"><option${ssrIncludeBooleanAttr(Array.isArray(roleFilter.value) ? ssrLooseContain(roleFilter.value, null) : ssrLooseEqual(roleFilter.value, null)) ? " selected" : ""}>All roles</option><option${ssrIncludeBooleanAttr(Array.isArray(roleFilter.value) ? ssrLooseContain(roleFilter.value, null) : ssrLooseEqual(roleFilter.value, null)) ? " selected" : ""}>Admin</option><option${ssrIncludeBooleanAttr(Array.isArray(roleFilter.value) ? ssrLooseContain(roleFilter.value, null) : ssrLooseEqual(roleFilter.value, null)) ? " selected" : ""}>Head</option><option${ssrIncludeBooleanAttr(Array.isArray(roleFilter.value) ? ssrLooseContain(roleFilter.value, null) : ssrLooseEqual(roleFilter.value, null)) ? " selected" : ""}>Staff</option></select><select class="h-9 rounded-md border bg-surface px-3 text-xs"><option${ssrIncludeBooleanAttr(Array.isArray(statusFilter.value) ? ssrLooseContain(statusFilter.value, null) : ssrLooseEqual(statusFilter.value, null)) ? " selected" : ""}>All statuses</option><option${ssrIncludeBooleanAttr(Array.isArray(statusFilter.value) ? ssrLooseContain(statusFilter.value, null) : ssrLooseEqual(statusFilter.value, null)) ? " selected" : ""}>Active</option><option${ssrIncludeBooleanAttr(Array.isArray(statusFilter.value) ? ssrLooseContain(statusFilter.value, null) : ssrLooseEqual(statusFilter.value, null)) ? " selected" : ""}>Disabled</option></select></section><div class="overflow-x-auto rounded-lg border bg-surface"><table class="w-full text-left text-xs"><thead class="bg-surface-muted text-2xs uppercase text-text-muted"><tr><!--[-->`);
        ssrRenderList([
          "Username",
          "Full Name",
          "Email",
          "Role",
          "MFA",
          "Status",
          "Last login",
          ""
        ], (heading) => {
          _push(`<th class="${ssrRenderClass(["px-3 py-2.5", heading === "" ? "w-40 text-right" : ""])}">${ssrInterpolate(heading)}</th>`);
        });
        _push(`<!--]--></tr></thead><tbody class="divide-y"><!--[-->`);
        ssrRenderList(filtered.value, (user) => {
          _push(`<tr><td class="px-3 py-3 font-mono">${ssrInterpolate(user[0])}</td><td class="px-3 py-3 font-medium">${ssrInterpolate(user[1])}</td><td class="px-3 py-3 text-text-muted">${ssrInterpolate(user[2])}</td><td class="px-3 py-3"><span class="rounded-full bg-primary-soft px-2 py-0.5 text-primary">${ssrInterpolate(user[3])}</span></td><td class="px-3 py-3"><span class="${ssrRenderClass([
            "rounded-full px-2 py-0.5",
            user[4] ? "bg-success-soft text-success" : "bg-warning-soft text-warning"
          ])}">${ssrInterpolate(user[4] ? "Enabled" : "Off")}</span></td><td class="px-3 py-3"><span class="${ssrRenderClass([
            "rounded-full px-2 py-0.5",
            user[5] ? "bg-success-soft text-success" : "bg-danger-soft text-danger"
          ])}">${ssrInterpolate(user[5] ? "Active" : "Disabled")}</span></td><td class="whitespace-nowrap px-3 py-3 text-text-muted">${ssrInterpolate(user[6])}</td><td class="w-40 whitespace-nowrap px-3 py-3"><div class="ml-auto grid w-36 grid-cols-[4rem_5rem] items-center justify-end text-left"><button class="inline-flex items-center gap-1 text-primary">`);
          _push(ssrRenderComponent(unref(IconKey), { size: 12 }, null, _parent));
          _push(`Reset </button><button class="text-left text-text-muted">${ssrInterpolate(user[5] ? "Deactivate" : "Activate")}</button></div></td></tr>`);
        });
        _push(`<!--]--></tbody></table><footer class="flex justify-between border-t px-3 py-2.5 text-xs text-text-muted"><span>Showing ${ssrInterpolate(filtered.value.length)} staff users</span><span>Page 1 of 1</span></footer></div>`);
        _push(ssrRenderComponent(_sfc_main$1, {
          modelValue: userDialog.value,
          "onUpdate:modelValue": ($event) => userDialog.value = $event,
          title: "Create staff user",
          description: "Add an account and assign its initial role and access.",
          size: "lg"
        }, {
          footer: withCtx(({ close }, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`<button class="rounded-md border px-4 py-2 text-xs"${_scopeId}>Cancel</button><button class="rounded-md bg-primary px-4 py-2 text-xs text-white"${_scopeId}> Create user </button>`);
            } else {
              return [
                createVNode("button", {
                  class: "rounded-md border px-4 py-2 text-xs",
                  onClick: close
                }, "Cancel", 8, ["onClick"]),
                createVNode("button", {
                  class: "rounded-md bg-primary px-4 py-2 text-xs text-white",
                  onClick: close
                }, " Create user ", 8, ["onClick"])
              ];
            }
          }),
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`<div class="grid gap-4 sm:grid-cols-2"${_scopeId}><label class="text-xs font-medium"${_scopeId}>Full name<input class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="Full name"${_scopeId}></label><label class="text-xs font-medium"${_scopeId}>Username<input class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="username"${_scopeId}></label><label class="text-xs font-medium"${_scopeId}>Email<input type="email" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="name@unifast.gov.ph"${_scopeId}></label><label class="text-xs font-medium"${_scopeId}>Role<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"${_scopeId}><option${_scopeId}>Staff</option><option${_scopeId}>Head</option><option${_scopeId}>Admin</option></select></label><label class="flex items-center gap-2 text-xs sm:col-span-2"${_scopeId}><input type="checkbox" checked${_scopeId}>Require password change and MFA enrollment on first sign-in</label></div>`);
            } else {
              return [
                createVNode("div", { class: "grid gap-4 sm:grid-cols-2" }, [
                  createVNode("label", { class: "text-xs font-medium" }, [
                    createTextVNode("Full name"),
                    createVNode("input", {
                      class: "mt-1.5 h-10 w-full rounded-md border px-3 text-sm",
                      placeholder: "Full name"
                    })
                  ]),
                  createVNode("label", { class: "text-xs font-medium" }, [
                    createTextVNode("Username"),
                    createVNode("input", {
                      class: "mt-1.5 h-10 w-full rounded-md border px-3 text-sm",
                      placeholder: "username"
                    })
                  ]),
                  createVNode("label", { class: "text-xs font-medium" }, [
                    createTextVNode("Email"),
                    createVNode("input", {
                      type: "email",
                      class: "mt-1.5 h-10 w-full rounded-md border px-3 text-sm",
                      placeholder: "name@unifast.gov.ph"
                    })
                  ]),
                  createVNode("label", { class: "text-xs font-medium" }, [
                    createTextVNode("Role"),
                    createVNode("select", { class: "mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm" }, [
                      createVNode("option", null, "Staff"),
                      createVNode("option", null, "Head"),
                      createVNode("option", null, "Admin")
                    ])
                  ]),
                  createVNode("label", { class: "flex items-center gap-2 text-xs sm:col-span-2" }, [
                    createVNode("input", {
                      type: "checkbox",
                      checked: ""
                    }),
                    createTextVNode("Require password change and MFA enrollment on first sign-in")
                  ])
                ])
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(ssrRenderComponent(_sfc_main$1, {
          modelValue: accountDialog.value,
          "onUpdate:modelValue": ($event) => accountDialog.value = $event,
          title: `${accountAction.value} account`,
          description: `${accountAction.value} for ${accountName.value}. This mock action does not persist.`,
          size: "sm"
        }, {
          footer: withCtx(({ close }, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`<button class="rounded-md border px-4 py-2 text-xs"${_scopeId}>Cancel</button><button class="rounded-md bg-primary px-4 py-2 text-xs text-white"${_scopeId}> Confirm </button>`);
            } else {
              return [
                createVNode("button", {
                  class: "rounded-md border px-4 py-2 text-xs",
                  onClick: close
                }, "Cancel", 8, ["onClick"]),
                createVNode("button", {
                  class: "rounded-md bg-primary px-4 py-2 text-xs text-white",
                  onClick: close
                }, " Confirm ", 8, ["onClick"])
              ];
            }
          }),
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              if (accountAction.value === "Reset password") {
                _push2(`<label class="text-xs font-medium"${_scopeId}>Reset method<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"${_scopeId}><option${_scopeId}>Send password reset email</option><option${_scopeId}>Generate temporary password</option><option${_scopeId}>Force reset at next login</option></select></label>`);
              } else {
                _push2(`<p class="text-sm text-text-muted"${_scopeId}> Confirm that you want to ${ssrInterpolate(accountAction.value.toLowerCase())} this user account. </p>`);
              }
            } else {
              return [
                accountAction.value === "Reset password" ? (openBlock(), createBlock("label", {
                  key: 0,
                  class: "text-xs font-medium"
                }, [
                  createTextVNode("Reset method"),
                  createVNode("select", { class: "mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm" }, [
                    createVNode("option", null, "Send password reset email"),
                    createVNode("option", null, "Generate temporary password"),
                    createVNode("option", null, "Force reset at next login")
                  ])
                ])) : (openBlock(), createBlock("p", {
                  key: 1,
                  class: "text-sm text-text-muted"
                }, " Confirm that you want to " + toDisplayString(accountAction.value.toLowerCase()) + " this user account. ", 1))
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`</div>`);
      } else {
        _push(`<div${ssrRenderAttrs(_attrs)}>`);
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/app/users",
          class: "mb-3 inline-flex items-center gap-1 text-xs text-text-muted hover:text-text"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(ssrRenderComponent(unref(IconArrowLeft), { size: 13 }, null, _parent2, _scopeId));
              _push2(`Back`);
            } else {
              return [
                createVNode(unref(IconArrowLeft), { size: 13 }),
                createTextVNode("Back")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`<header class="mb-4"><h1 class="text-2xl font-semibold tracking-tight">Permission Matrix</h1><p class="mt-1 text-sm text-text-muted">Module-level permissions per role.</p></header><!--[-->`);
        ssrRenderList(permissionGroups, (group) => {
          _push(`<section class="mb-4"><p class="mb-1.5 text-2xs font-medium uppercase tracking-wide text-text-muted">${ssrInterpolate(group.module)}</p><div class="overflow-hidden rounded-lg border bg-surface"><table class="w-full text-left text-xs"><thead class="bg-surface-muted text-2xs uppercase text-text-muted"><tr><th class="px-3 py-2.5">Permission</th><!--[-->`);
          ssrRenderList(["Admin", "Head", "Staff"], (role) => {
            _push(`<th class="px-3 py-2.5">${ssrInterpolate(role)}</th>`);
          });
          _push(`<!--]--></tr></thead><tbody class="divide-y"><!--[-->`);
          ssrRenderList(group.permissions, (permission) => {
            _push(`<tr><td class="px-3 py-3 capitalize">${ssrInterpolate(permission)}</td><!--[-->`);
            ssrRenderList(["Admin", "Head", "Staff"], (role) => {
              _push(`<td class="px-3 py-3">`);
              if (allowed(role, permission)) {
                _push(ssrRenderComponent(unref(IconCheck), {
                  size: 14,
                  class: "text-success"
                }, null, _parent));
              } else {
                _push(ssrRenderComponent(unref(IconX), {
                  size: 14,
                  class: "text-text-soft"
                }, null, _parent));
              }
              _push(`</td>`);
            });
            _push(`<!--]--></tr>`);
          });
          _push(`<!--]--></tbody></table></div></section>`);
        });
        _push(`<!--]--></div>`);
      }
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/users/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
