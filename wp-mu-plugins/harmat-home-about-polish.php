<?php
/**
 * Plugin Name: Harmat Home About Polish
 * Description: Visual polish for the homepage project introduction block without changing public text.
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_home_about_polish_is_target() {
    return !is_admin() && (is_front_page() || is_page(164));
}

function harmat_home_about_polish_css() {
    if (!harmat_home_about_polish_is_target()) {
        return;
    }
    ?>
    <style id="harmat-home-about-polish-css">
      body.home .elementor-element-d60b1b2 {
        padding: clamp(54px, 6vw, 92px) 0 clamp(86px, 8vw, 132px) !important;
        background:
          radial-gradient(circle at 14% 8%, rgba(168,116,42,.16), transparent 34%),
          radial-gradient(circle at 86% 18%, rgba(23,99,79,.10), transparent 28%),
          linear-gradient(180deg, #fff 0%, #f3eadb 48%, #fff 100%) !important;
      }
      body.home .harmat-about-remake {
        width: min(1420px, calc(100% - 36px)) !important;
      }
      body.home .harmat-about-grid {
        grid-template-columns: minmax(420px, .72fr) minmax(720px, 1.28fr) !important;
        gap: 24px !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
      }
      body.home .harmat-about-copy {
        padding: clamp(54px, 5vw, 82px) clamp(42px, 5vw, 72px) !important;
        border: 1px solid rgba(168,116,42,.18) !important;
        border-radius: 30px !important;
        background:
          linear-gradient(135deg, rgba(255,255,255,.98), rgba(252,247,237,.96)) !important;
        box-shadow: 0 28px 70px rgba(38,47,50,.10) !important;
      }
      body.home .harmat-about-copy::before {
        left: clamp(42px, 5vw, 72px) !important;
        width: 112px !important;
        height: 5px !important;
      }
      body.home .harmat-about-eyebrow {
        min-height: 30px !important;
        margin-bottom: clamp(30px, 3vw, 46px) !important;
        padding: 0 16px !important;
        background: rgba(255,255,255,.78) !important;
        letter-spacing: .18em !important;
      }
      body.home .harmat-about-copy h2 {
        max-width: 520px !important;
        margin-bottom: 24px !important;
        color: #102f38 !important;
        font-size: clamp(44px, 4.7vw, 72px) !important;
        line-height: .96 !important;
        letter-spacing: -.02em !important;
      }
      body.home .harmat-about-copy p {
        max-width: 540px !important;
        margin-bottom: 34px !important;
        color: #5f686c !important;
        font-size: clamp(15px, 1.2vw, 17px) !important;
        line-height: 1.9 !important;
      }
      body.home .harmat-about-meta {
        gap: 14px !important;
        margin-bottom: 18px !important;
      }
      body.home .harmat-about-meta-item {
        min-height: 126px !important;
        padding: 22px 22px 20px !important;
        border-color: rgba(168,116,42,.22) !important;
        background: rgba(255,255,255,.88) !important;
        box-shadow: 0 12px 32px rgba(38,47,50,.045) !important;
      }
      body.home .harmat-about-meta-item:nth-child(2) {
        background: #17634f !important;
        border-color: #17634f !important;
        color: #fff !important;
      }
      body.home .harmat-about-meta-item:nth-child(2) span,
      body.home .harmat-about-meta-item:nth-child(2) strong {
        color: #fff !important;
      }
      body.home .harmat-about-meta-item span {
        margin-bottom: 12px !important;
        letter-spacing: .15em !important;
      }
      body.home .harmat-about-meta-item strong {
        font-size: clamp(28px, 2.1vw, 38px) !important;
      }
      body.home .harmat-about-list {
        gap: 12px !important;
      }
      body.home .harmat-about-list li {
        min-height: 48px !important;
        padding: 13px 16px 13px 34px !important;
        border-color: rgba(168,116,42,.18) !important;
        background: rgba(255,255,255,.78) !important;
        color: #263238 !important;
        font-size: 12px !important;
        font-weight: 800 !important;
      }
      body.home .harmat-about-list li::before {
        top: 20px !important;
        left: 16px !important;
      }
      body.home .harmat-about-map,
      body.home .harmat-about-visual {
        min-height: clamp(620px, 52vw, 780px) !important;
        border: 1px solid rgba(168,116,42,.18) !important;
        border-radius: 30px !important;
        box-shadow: 0 28px 70px rgba(38,47,50,.12) !important;
      }
      body.home .harmat-about-map-image,
      body.home .harmat-about-visual img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        object-position: center center !important;
        filter: saturate(1.06) contrast(1.03) !important;
        border-radius: 30px !important;
      }
      body.home .harmat-visual-badges,
      body.home .harmat-about-map .harmat-visual-badges {
        display: none !important;
      }
      body.home .harmat-about-stats {
        display: grid !important;
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 18px !important;
        margin-top: 24px !important;
      }
      body.home .harmat-about-stat {
        min-height: 148px !important;
        padding: 28px 30px !important;
        border: 1px solid rgba(168,116,42,.18) !important;
        border-radius: 22px !important;
        background: rgba(255,255,255,.94) !important;
        box-shadow: 0 20px 48px rgba(38,47,50,.08) !important;
      }
      body.home .harmat-about-stat::before {
        width: 58px !important;
        height: 4px !important;
        background: #a8742a !important;
      }
      body.home .harmat-about-stat strong {
        color: #102f38 !important;
        font-size: clamp(38px, 3.2vw, 52px) !important;
        line-height: 1 !important;
      }
      body.home .harmat-about-stat small {
        margin-left: 5px !important;
        color: #667078 !important;
        font-size: 12px !important;
        font-family: Montserrat, Arial, sans-serif !important;
        text-transform: uppercase !important;
      }
      body.home .harmat-about-stat span {
        margin-top: 14px !important;
        color: #667078 !important;
        font-size: 11px !important;
        font-weight: 900 !important;
        letter-spacing: .08em !important;
        text-transform: uppercase !important;
      }
      @media (max-width: 1100px) {
        body.home .harmat-about-grid {
          grid-template-columns: 1fr !important;
        }
        body.home .harmat-about-map,
        body.home .harmat-about-visual {
          min-height: 440px !important;
          border-left: 0 !important;
          border-top: 1px solid rgba(168,116,42,.18) !important;
        }
        body.home .harmat-about-stats {
          grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
      }
      @media (max-width: 640px) {
        body.home .elementor-element-d60b1b2 {
          padding: 28px 0 58px !important;
        }
        body.home .harmat-about-remake {
          width: calc(100% - 24px) !important;
        }
        body.home .harmat-about-copy {
          padding: 34px 24px !important;
        }
        body.home .harmat-about-copy::before {
          left: 24px !important;
          width: 72px !important;
        }
        body.home .harmat-about-copy h2 {
          font-size: 42px !important;
        }
        body.home .harmat-about-meta,
        body.home .harmat-about-list,
        body.home .harmat-about-stats {
          grid-template-columns: 1fr !important;
        }
        body.home .harmat-about-map,
        body.home .harmat-about-visual {
          min-height: 300px !important;
        }
      }
    </style>
    <?php
}
add_action('wp_head', 'harmat_home_about_polish_css', 95);
