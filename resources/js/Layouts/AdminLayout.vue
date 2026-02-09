<script setup>
import { Link } from '@inertiajs/vue3'

</script>

<template>
  <!-- Layout wrapper -->
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <!-- Menu (Sidebar) -->
      <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
        <div class="app-brand demo">
          <Link :href="route('admin.dashboard')" class="app-brand-link">
            <span class="app-brand-text demo menu-text fw-bolder ms-2">Market Sharks</span>
          </Link>

          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
          </a>
        </div>

        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
          <li class="menu-item">
            <Link :href="route('admin.dashboard')" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div>Dashboard</div>
            </Link>
          </li>
          <!-- Components -->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Components</span></li>
          <!-- Cards -->
          <li class="menu-item">
            <Link :href="route('admin.users.index')" class="menu-link">
              <i class="menu-icon tf-icons bx bx-collection"></i>
              <div data-i18n="Basic">Users</div>
            </Link>
          </li>

          <li class="menu-item" v-if="$page.props.auth.role_names?.includes('super_admin')">
              <Link :href="route('admin.admins.index')" class="menu-link">
                <i class="menu-icon tf-icons bx bx-collection"></i>
                <div data-i18n="Basic">Admins</div>
              </Link>
            </li>
        </ul>
      </aside>
      <!-- / Menu -->

      <!-- Layout page -->
      <div class="layout-page">
        <!-- Navbar -->
        <nav
          class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
          id="layout-navbar"
        >
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
              <i class="bx bx-menu bx-sm"></i>
            </a>
          </div>

          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <div class="navbar-nav align-items-center">
              <div class="nav-item d-flex align-items-center">
                <i class="bx bx-search fs-4 lh-0"></i>
                <input
                  type="text"
                  class="form-control border-0 shadow-none"
                  placeholder="Search..."
                  aria-label="Search..."
                />
              </div>
            </div>
        <!-- Right side -->
        <ul class="navbar-nav flex-row align-items-center ms-auto">
          <!-- User -->
          <li class="nav-item navbar-dropdown dropdown-user dropdown">
            <a
              class="nav-link dropdown-toggle hide-arrow"
              href="javascript:void(0);"
              data-bs-toggle="dropdown"
            >
              <div class="avatar avatar-online">
                <img
                  src="/admin/assets/img/avatars/1.png"
                  alt
                  class="w-px-40 h-auto rounded-circle"
                />
              </div>
            </a>

            <ul class="dropdown-menu dropdown-menu-end">
              <li>
                <a class="dropdown-item" href="#">
                  <div class="d-flex">
                    <div class="flex-shrink-0 me-3">
                      <div class="avatar avatar-online">
                        <img
                          src="/admin/assets/img/avatars/1.png"
                          alt
                          class="w-px-40 h-auto rounded-circle"
                        />
                      </div>
                    </div>
                    <div class="flex-grow-1">
                      <span class="fw-semibold d-block">
                        {{ $page.props.auth.user.name }}
                      </span>
                      <small class="text-muted">Admin</small>
                    </div>
                  </div>
                </a>
              </li>

              <li><div class="dropdown-divider"></div></li>

              <li>
                <a class="dropdown-item" href="#">
                  <i class="bx bx-user me-2"></i>
                  <span class="align-middle">My Profile</span>
                </a>
              </li>

              <li>
                <a class="dropdown-item" href="#">
                  <i class="bx bx-cog me-2"></i>
                  <span class="align-middle">Settings</span>
                </a>
              </li>

              <li>
                <a class="dropdown-item" href="#">
                  <span class="d-flex align-items-center align-middle">
                    <i class="flex-shrink-0 bx bx-credit-card me-2"></i>
                    <span class="flex-grow-1 align-middle">Billing</span>
                    <span
                      class="flex-shrink-0 badge badge-center rounded-pill bg-danger w-px-20 h-px-20"
                      >4</span
                    >
                  </span>
                </a>
              </li>

              <li><div class="dropdown-divider"></div></li>

              <li>
                <Link
                  :href="route('logout')"
                  method="post"
                  class="dropdown-item"
                >
                  <i class="bx bx-power-off me-2"></i>
                  <span class="align-middle">Log Out</span>
                </Link>
              </li>
            </ul>
          </li>
          <!--/ User -->
        </ul>

          </div>
        </nav>
        <!-- / Navbar -->

        <!-- Content wrapper -->
        <div class="content-wrapper">
          <!-- Content -->
          <div class="container-xxl flex-grow-1 container-p-y">
            <slot />
          </div>
          <!-- / Content -->

          <!-- Footer (optional) -->
          <footer class="content-footer footer bg-footer-theme">
            <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
              <div class="mb-2 mb-md-0">
                © {{ new Date().getFullYear() }} Market Sharks
              </div>
            </div>
          </footer>

          <div class="content-backdrop fade"></div>
        </div>
        <!-- / Content wrapper -->
      </div>
      <!-- / Layout page -->
    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
  </div>
  <!-- / Layout wrapper -->
</template>