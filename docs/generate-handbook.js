/**
 * Fluro Tech — Owner's Handbook generator.
 * Run: node generate-handbook.js
 * Output: ./Fluro-Tech-Owner-Handbook.docx
 */
const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
  Header, Footer, AlignmentType, LevelFormat,
  TableOfContents, HeadingLevel, BorderStyle, WidthType, ShadingType,
  VerticalAlign, PageNumber, PageBreak,
} = require('docx');

// ---------- Brand ----------
const COLOR = {
  brand:       '10A648',   // brand-700, body accents
  brandDark:   '0F943F',   // brand-800
  brandLight:  'D2FFE5',   // brand-100, table-row tint
  brandTint:   'ECFFF5',   // brand-50, banner backgrounds
  ink:         '111827',   // gray-900
  body:        '374151',   // gray-700
  muted:       '6B7280',   // gray-500
  hairline:    'D1D5DB',   // gray-300
  warn:        'B45309',   // amber-700
  danger:      'B91C1C',   // red-700
};

// ---------- Page geometry (US Letter) ----------
const PAGE_W = 12240;
const PAGE_H = 15840;
const MARGIN = 1440;
const CONTENT_W = PAGE_W - 2 * MARGIN;     // 9360

// ---------- Helpers ----------
const t = (text, opts = {}) => new TextRun({ text, ...opts });
const b = (text, opts = {}) => new TextRun({ text, bold: true, ...opts });

const p = (runs, opts = {}) => new Paragraph({
  children: Array.isArray(runs) ? runs : [typeof runs === 'string' ? t(runs) : runs],
  spacing: { after: 120 },
  ...opts,
});

const h1 = (text) => new Paragraph({
  heading: HeadingLevel.HEADING_1,
  children: [t(text)],
  pageBreakBefore: true,
});

const h2 = (text) => new Paragraph({
  heading: HeadingLevel.HEADING_2,
  children: [t(text)],
});

const h3 = (text) => new Paragraph({
  heading: HeadingLevel.HEADING_3,
  children: [t(text)],
});

const bullet = (children, level = 0) => new Paragraph({
  numbering: { reference: 'bullets', level },
  children: Array.isArray(children) ? children : [typeof children === 'string' ? t(children) : children],
  spacing: { after: 60 },
});

const numbered = (children, level = 0) => new Paragraph({
  numbering: { reference: 'numbered', level },
  children: Array.isArray(children) ? children : [typeof children === 'string' ? t(children) : children],
  spacing: { after: 60 },
});

const spacer = (size = 200) => new Paragraph({
  spacing: { after: size },
  children: [t('')],
});

// Standard cell border colour for tables.
const cellBorder = { style: BorderStyle.SINGLE, size: 4, color: COLOR.hairline };
const cellBorders = { top: cellBorder, bottom: cellBorder, left: cellBorder, right: cellBorder };
const cellMargins = { top: 100, bottom: 100, left: 150, right: 150 };

/**
 * Build a header-row + body-rows table. `widths` must sum to `tableWidth`
 * (defaulting to CONTENT_W).
 */
function makeTable(headers, rows, widths, tableWidth = CONTENT_W) {
  // Header row
  const headerRow = new TableRow({
    tableHeader: true,
    children: headers.map((label, i) => new TableCell({
      borders: cellBorders,
      width: { size: widths[i], type: WidthType.DXA },
      shading: { fill: COLOR.brand, type: ShadingType.CLEAR },
      margins: cellMargins,
      verticalAlign: VerticalAlign.CENTER,
      children: [new Paragraph({
        children: [new TextRun({ text: label, bold: true, color: 'FFFFFF', size: 22 })],
      })],
    })),
  });

  // Body rows, with zebra striping (light brand tint on even rows)
  const bodyRows = rows.map((row, rIdx) => {
    const fill = rIdx % 2 === 1 ? COLOR.brandTint : 'FFFFFF';
    return new TableRow({
      children: row.map((cell, i) => new TableCell({
        borders: cellBorders,
        width: { size: widths[i], type: WidthType.DXA },
        shading: { fill, type: ShadingType.CLEAR },
        margins: cellMargins,
        verticalAlign: VerticalAlign.TOP,
        children: Array.isArray(cell)
          ? cell // pre-built Paragraph(s)
          : [new Paragraph({
              children: [new TextRun({ text: String(cell), size: 22, color: COLOR.body })],
              spacing: { after: 0 },
            })],
      })),
    });
  });

  return new Table({
    width: { size: tableWidth, type: WidthType.DXA },
    columnWidths: widths,
    rows: [headerRow, ...bodyRows],
  });
}

/** Highlight call-out box (single-cell shaded table). */
function callout(title, body, tint = COLOR.brandLight) {
  return new Table({
    width: { size: CONTENT_W, type: WidthType.DXA },
    columnWidths: [CONTENT_W],
    rows: [new TableRow({
      children: [new TableCell({
        borders: cellBorders,
        width: { size: CONTENT_W, type: WidthType.DXA },
        shading: { fill: tint, type: ShadingType.CLEAR },
        margins: { top: 200, bottom: 200, left: 240, right: 240 },
        children: [
          new Paragraph({
            children: [new TextRun({ text: title, bold: true, color: COLOR.brandDark, size: 22 })],
            spacing: { after: 80 },
          }),
          new Paragraph({
            children: [new TextRun({ text: body, color: COLOR.body, size: 22 })],
            spacing: { after: 0 },
          }),
        ],
      })],
    })],
  });
}

// ---------- Content ----------
const TODAY = 'May 27, 2026';

/* ===== Title page ===== */
const titlePage = [
  spacer(2400),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { after: 200 },
    children: [new TextRun({ text: 'FLURO TECH', size: 64, bold: true, color: COLOR.brand })],
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { after: 600 },
    children: [new TextRun({ text: 'Owner’s Handbook', size: 44, color: COLOR.ink })],
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { after: 200 },
    children: [new TextRun({
      text: 'Your day-to-day guide to running the storefront, the admin panel, and everything in between.',
      italics: true, size: 24, color: COLOR.muted,
    })],
  }),
  spacer(600),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    children: [new TextRun({ text: TODAY, size: 22, color: COLOR.muted })],
  }),
  spacer(2000),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    children: [new TextRun({
      text: 'Melpuram Junction · Pacode (P.O), Kanniyakumari District, Tamil Nadu – 629168',
      size: 18, color: COLOR.muted,
    })],
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    children: [new TextRun({
      text: '+91 96774 09009  ·  Flurotech46@gmail.com  ·  @fluro_tech_',
      size: 18, color: COLOR.muted,
    })],
  }),
];

/* ===== Table of Contents ===== */
const tocPage = [
  new Paragraph({
    pageBreakBefore: true,
    heading: HeadingLevel.HEADING_1,
    children: [t('Contents')],
  }),
  new TableOfContents('Contents', { hyperlink: true, headingStyleRange: '1-3' }),
];

/* ===== Section 1: Welcome ===== */
const sec1 = [
  h1('1. Welcome & overview'),
  p('Fluro Tech is an end-to-end online laptop store. This handbook is your operations guide — it walks you through what your shoppers see, how you keep things running from the admin panel, and the day-to-day workflows that tie the two together.'),
  p('The platform is made up of three connected pieces:'),
  spacer(120),
  makeTable(
    ['Component', 'Audience', 'What it does'],
    [
      ['Customer site', 'Public shoppers', 'The branded storefront where visitors browse products, ask questions, sign up, leave reviews, and manage their own account.'],
      ['Admin panel', 'You (and any staff with the admin role)', 'The private back-office for products, offers, customers, enquiries, reviews, and notifications.'],
      ['Backend API', 'Both of the above', 'The Laravel-powered service that stores all data and enforces business rules. Customers and admins both talk to it.'],
    ],
    [2160, 2700, 4500]
  ),
  spacer(160),
  callout(
    'In short',
    'Your customers experience Fluro Tech through the customer site; you run it through the admin panel; the backend is the single source of truth that connects both.'
  ),
];

/* ===== Section 2: Quick reference ===== */
const sec2 = [
  h1('2. Quick reference'),
  p('Where everything lives during local operation:'),
  spacer(120),
  makeTable(
    ['Component', 'URL'],
    [
      ['Customer site', 'http://localhost:5173'],
      ['Admin panel', 'http://localhost:5174'],
      ['Backend API', 'http://127.0.0.1:8000'],
    ],
    [3000, 6360]
  ),
  spacer(240),
  h2('Default admin login'),
  p([
    t('Use these credentials to sign in at the admin panel for the first time. You should change the password from '),
    b('Profile'),
    t(' as soon as you are comfortable.'),
  ]),
  makeTable(
    ['Field', 'Value'],
    [
      ['Email', 'admin@example.com'],
      ['Password', 'password'],
    ],
    [3000, 6360]
  ),
  spacer(160),
  callout(
    'Customer accounts are self-service',
    'There is no “create customer” button in the admin panel. Customers register themselves at /register on the storefront. Admins can only deactivate or delete accounts.'
  ),
];

/* ===== Section 3: Customer experience ===== */
const sec3 = [
  h1('3. The customer experience'),
  p('This section walks through what a shopper sees from landing page to their personal dashboard. Use it to train staff, or as a script when demoing the site.'),

  h2('3.1 Homepage'),
  p('The first impression. From top to bottom:'),
  bullet([b('Announcement bar'), t(' — a gradient strip at the very top, rotating through whatever banners you have set in '), b('Admin > Announcements'), t('. The customer can dismiss it.')]),
  bullet([b('Sticky header'), t(' — logo, search, primary nav (Home / Products / Coming Soon / About / Contact). When the shopper signs in, this strip also shows a notification bell and account dropdown.')]),
  bullet([b('Hero section'), t(' — fluorescent green block with the "Pick a laptop you’ll love" pitch and two CTAs (Shop now, Why us).')]),
  bullet([b('Featured products'), t(' — a four-up grid of products you marked as Featured in the admin panel.')]),
  bullet([b('Accessories'), t(' — a quick row of accessories pulled from that category.')]),
  bullet([b('Coming Soon teasers'), t(' — a horizontal strip if any upcoming products are configured.')]),
  bullet([b('Recently viewed'), t(' — a horizontal strip of products this browser has looked at. Lives in localStorage; nothing is sent to the server.')]),
  bullet([b('Recommendation tile'), t(' — a gradient call-to-action card that drops the user into /contact for a personalised pick.')]),
  bullet([b('Footer'), t(' — brand, address, social links, payment badges, system-status line.')]),

  h2('3.2 Browsing & search'),
  p('Two complementary paths to find a product:'),
  numbered([b('Top-bar search'), t(' — type and press Enter. The shopper lands on /products with the term in the URL (e.g. /products?q=lenovo). The Products page reads the URL and filters live.')]),
  numbered([b('Products page filters'), t(' — a sticky sidebar with search, category pills, brand, and a min/max price range. A breadcrumb (Home > Products) sits above the grid.')]),
  p('Every product card hovers up, fades a "View details" CTA, and shows the wishlist heart in the top-right corner. Cards also show offer badges (e.g. “-15%”, “BUNDLE”, “NEW”, “FEATURED”).'),

  h2('3.3 Product detail page'),
  p('The most important page for converting a shopper. It carries seven distinct elements:'),
  bullet([b('Breadcrumbs'), t(' — Home > Products > Category > Product name.')]),
  bullet([b('Image gallery with hover-zoom'), t(' — move the mouse over the main image to see a 2× zoomed view that follows the cursor. Thumbnails below switch the active image.')]),
  bullet([b('Title, brand, category, rating, price'), t(' — plus a Wishlist heart and a Share button at the top right.')]),
  bullet([b('Offers panel'), t(' — surfaces all live offers (percent off, flat off, or bundle deals). Bundles show the bundled product’s image inline.')]),
  bullet([b('Description and specs'), t(' — product copy followed by a key-value card of specifications you entered when creating the product.')]),
  bullet([b('Enquire about this product'), t(' — a button that, when clicked, expands an inline form. Logged-in customers can ask a question; guests are redirected to /login.')]),
  bullet([b('Customer reviews'), t(' — already-approved reviews appear in a list; below it is the Write-a-review form (rating + comment). Guests see a sign-in CTA instead.')]),
  bullet([b('You may also like'), t(' — up to six related products from the same category.')]),
  bullet([b('Recently viewed'), t(' — a horizontal strip excluding the current product.')]),

  h2('3.4 Coming Soon page'),
  p('A teaser feed of products you have queued in Admin > Upcoming products. Each card shows the brand, name, optional expected date, optional teaser price, and a placeholder image. Useful for building anticipation before a launch.'),

  h2('3.5 About and Contact pages'),
  bullet([b('About'), t(' — brand story, why-us, location card with the Melpuram Junction address, and a stat strip (50K+ happy buyers, 4.9★ avg rating, etc.).')]),
  bullet([b('Contact'), t(' — phone, email, address, Instagram chip, plus a general-purpose enquiry form (logged-in customers only).')]),

  h2('3.6 Signing up and signing in'),
  p('Three pages handle this flow:'),
  numbered([b('/register'), t(' — name, email, phone (optional), password (min 6 chars), confirm. On success the user is signed in and dropped onto the homepage.')]),
  numbered([b('/login'), t(' — email + password. A subtle "Forgot password?" link sits to the right of the password label.')]),
  numbered([b('/forgot-password'), t(' — the customer enters their email. The screen always shows the same generic success message regardless of whether the email is registered (this prevents “does this email exist?” attacks). In local development an amber DEV SHORTCUT card also shows the actual reset URL.')]),
  numbered([b('/reset-password'), t(' — reached from the email link. The customer enters a new password and is automatically signed in upon success.')]),

  h2('3.7 My Account / Customer dashboard'),
  p('Once signed in, the customer can open the avatar dropdown in the top-right and click “My dashboard”. The dashboard has six sub-pages, accessible from a left sidebar:'),
  spacer(120),
  makeTable(
    ['Page', 'What the customer sees'],
    [
      ['Overview', 'Welcome banner, three KPI cards (Open enquiries, My reviews, Unread updates with a pulsing dot when > 0), and a list of the three most recent enquiries.'],
      ['My enquiries', 'Pill filters (All / New / In progress / Responded / Closed) and a card list. Tapping a card opens a chat-thread view with the customer’s original message and the admin’s reply (if any).'],
      ['Wishlist', 'A grid of every product the customer has hearted. Same ProductCard component as the storefront.'],
      ['My reviews', 'Every review the customer has submitted, with rating, product name, status pill (pending / approved), and timestamp.'],
      ['Notifications', 'A scrollable feed of replies + status changes. Unread items have a pulsing brand-green dot. “Mark all read” button.'],
      ['Profile', 'Name and phone editable. Email is read-only. Optional password-change section requires the current password.'],
    ],
    [2200, 7160]
  ),
  spacer(120),
  callout(
    'The notification bell',
    'The customer site Navbar also shows a bell icon when signed in. It polls every 30 seconds for unread notifications, shows a count badge with a pulsing halo, and opens a dropdown with the same items as the /dashboard/notifications page. Tapping an item marks it read and navigates to the right place.'
  ),
];

/* ===== Section 4: Admin experience ===== */
const sec4 = [
  h1('4. The admin experience'),
  p('Sign in at http://localhost:5174 with the admin credentials. The admin panel uses a dark fluorescent-green sidebar grouped into four sections: Overview, Catalogue, Marketing, and Feedback. The topbar shows the current page title, a quick-search box, and a notification bell.'),

  h2('4.1 Dashboard (Overview)'),
  p('Your at-a-glance health screen. From the top:'),
  bullet([b('Welcome banner'), t(' — dark gradient with today’s date and your name.')]),
  bullet([b('Four KPI cards'), t(' — Total products, Pending enquiries, Pending reviews, Total enquiries. Each card is a deep-link to the relevant page and shows a contextual delta line (e.g. "Needs reply" when there are pending enquiries).')]),
  bullet([b('Recent enquiries panel'), t(' — the latest customer questions with sender, status, message preview, and time-ago.')]),
  bullet([b('Recent reviews panel'), t(' — the latest reviews with rating, status, and product name.')]),
  bullet([b('Quick links'), t(' — a row of four gradient tiles linking to Offers, Upcoming products, Announcements, and Categories.')]),

  h2('4.2 Notifications (Overview > Notifications)'),
  p('The bell in the topbar is the fast path. It polls every 30 seconds, badges in fluorescent green with a count, and opens a dropdown showing the latest 15 notifications. From the dropdown you can:'),
  bullet('Click any item to mark it read and jump to the related page.'),
  bullet('Hit "Mark all read" to clear the badge.'),
  bullet('Click "See all notifications" at the bottom to open the full /notifications page.'),
  p('The full page adds filter pills (All / Unread / Enquiries / Reviews / Low stock) and per-item Delete / Mark read actions. Notifications are paginated at 15 per page.'),

  h2('4.3 Customers (Overview > Customers)'),
  p('A read-only roster of every signed-up customer (and any other admins). For each row you have:'),
  bullet([b('Search'), t(' — by name, email, or phone.')]),
  bullet([b('Status pills'), t(' — All / Active / Inactive. Click the status pill on a row to instantly flip it.')]),
  bullet([b('Role pills'), t(' — Customers / Admins.')]),
  bullet([b('View'), t(' — opens a right-side drawer with the customer’s contact details, enquiry history, and review history. From the drawer you can also Activate or Deactivate them.')]),
  bullet([b('Delete'), t(' — permanent. Removes their enquiries, reviews, and any pending notifications.')]),
  spacer(120),
  callout(
    'Editing customer details is intentionally disabled',
    'Admins can view, deactivate, and delete customer records, but cannot edit the name, email, phone, or password of a customer. Customers manage those fields themselves from their own Profile page. This keeps the trust boundary clean.'
  ),

  h2('4.4 Products (Catalogue > Products)'),
  p('The catalogue. Each row shows a thumbnail, name, brand, category, price, stock, and status. Actions per row: Edit, Delete. The bar above the table has a search box and an "+ Add product" button.'),
  bullet([b('Stock adjuster'), t(' — the Stock column has a "– / number / +" trio. Click the number to inline-edit it; press Enter to save.')]),
  bullet([b('Status badges'), t(' — active / inactive (visible vs hidden on the storefront) and featured (shown on the homepage).')]),
  bullet([b('Low-stock auto alert'), t(' — the moment stock for a product drops to 5 or below, an automatic notification is added to the admin bell. Restocking back above 5 quietly clears that alert.')]),

  h2('4.5 Categories (Catalogue > Categories)'),
  p('A split-screen page. The left side is a table of every category with its slug and number of products. The right side is a single form that doubles as Add and Edit. Click Edit on a row, the form populates; click Cancel to return to add-mode.'),

  h2('4.6 Upcoming products (Catalogue > Upcoming)'),
  p('Teasers that show in the customer site’s Coming Soon page and homepage strip. Each row has name, brand, optional expected date, optional price estimate, image, and an active/hidden toggle. Useful for hype before stock lands.'),

  h2('4.7 Offers (Marketing > Offers)'),
  p('Three offer types are supported:'),
  spacer(120),
  makeTable(
    ['Type', 'Use it when', 'What customers see'],
    [
      ['Percent off', 'Standard discount on the main product.', 'Strike-through original price plus the discounted price on the product card and detail page.'],
      ['Flat off', 'Fixed rupee discount on the main product.', 'Strike-through original price plus the new price, with a "Save ₹X" tag.'],
      ['Bundle', 'Free or discounted accessory with the main product.', 'A green gift card under the price showing the bundled product and its discount (e.g. FREE or –50%).'],
    ],
    [1800, 3500, 4060]
  ),
  spacer(120),
  p('Each offer has optional start/end dates. Live offers automatically appear in the customer site’s offer panel; expired or future-dated offers stay hidden.'),

  h2('4.8 Announcements (Marketing > Announcements)'),
  p('The strip at the very top of the customer site. Multiple announcements rotate every 6 seconds. Each announcement has:'),
  bullet([b('Title and subtitle'), t(' — the headline and supporting copy.')]),
  bullet([b('Badge label'), t(' — short caps text like "DIWALI" or "SALE".')]),
  bullet([b('Theme'), t(' — four gradient choices: Festival, Sale, Coming Soon, Info.')]),
  bullet([b('CTA label and URL'), t(' — an optional button leading anywhere on the site.')]),
  bullet([b('Window'), t(' — optional start / end dates. Outside the window the announcement is "scheduled" or "inactive" and never reaches customers.')]),
  bullet([b('Sort order'), t(' — controls rotation order when multiple are live.')]),

  h2('4.9 Reviews (Feedback > Reviews)'),
  p('Customer reviews go into a moderation queue. Default tab is Pending; tabs are Pending / Approved / All. Each row shows customer name + email, the product, the rating in stars, the comment (clamped to 3 lines), and the current status pill.'),
  bullet([b('Approve'), t(' — makes the review visible on the product page.')]),
  bullet([b('Delete'), t(' — removes the review and clears the related notification.')]),

  h2('4.10 Enquiries (Feedback > Enquiries)'),
  p('Customer questions flow through a four-state lifecycle:'),
  spacer(120),
  makeTable(
    ['Status', 'When it applies', 'Customer sees'],
    [
      ['New', 'The default when a customer first sends an enquiry.', 'A yellow "New" pill on their My enquiries page.'],
      ['In progress', 'You’ve seen it and are looking into it.', 'A sky-blue "In progress" pill, plus a dashboard notification: "Your enquiry is now in progress."'],
      ['Responded', 'Auto-set the moment you save a reply.', 'A violet "Responded" pill, plus a notification: "You have a new reply on your enquiry."'],
      ['Closed', 'The conversation is finished.', 'An emerald "Closed" pill, plus a notification: "Your enquiry is now closed."'],
    ],
    [1600, 3200, 4560]
  ),
  spacer(120),
  p('To reply: click any row to open the detail modal. You see the original message, a Status dropdown, and a Reply textarea. Hit "Save & notify" — the customer is notified on their dashboard immediately. Reviews are still moderated separately and do not flow through this state machine.'),
];

/* ===== Section 5: Workflows ===== */
const sec5 = [
  h1('5. Day-in-the-life workflows'),
  p('Concrete step-by-step examples to anchor your mental model.'),

  h2('Workflow A. A customer asks a question about a laptop'),
  numbered('Customer browses to /products/3 (Lenovo ThinkPad).'),
  numbered('Customer clicks "Enquire about this product" — if logged out, the button is "Sign in to enquire", which redirects to /login.'),
  numbered('Customer types the question and clicks "Send enquiry →". The form shows a green success banner.'),
  numbered('In the admin bell at http://localhost:5174 a new green badge appears. The dropdown lists "New enquiry from {customer name} about {product}".'),
  numbered('Admin clicks the bell item → lands on /enquiries with the new row at the top, status "New".'),
  numbered('Admin clicks "Open" → the modal opens with the message. Admin types a reply and clicks "Save & notify".'),
  numbered('Status auto-flips to "Responded". The admin bell notification is auto-marked read.'),
  numbered('On the customer site, the customer’s bell ticks up. Clicking it shows "You have a new reply on your enquiry". Clicking that opens the chat thread at /dashboard/enquiries/{id}.'),
  numbered('Once both parties are happy, the admin can set status to "Closed" — a final notification fires for the customer.'),

  h2('Workflow B. Running a festival sale'),
  numbered([t('Go to '), b('Admin > Announcements'), t(' and click "+ New announcement".')]),
  numbered('Pick the "Festival" theme, enter a Title ("Diwali Sale — up to 30% off!"), Subtitle, optional CTA pointing to /products, and Active = true. Save.'),
  numbered([t('Then go to '), b('Admin > Offers'), t(' and click "+ New offer". For each laptop you want discounted, pick the product, choose the type (Percent / Flat / Bundle), and set the value plus the window.')]),
  numbered('Done — the announcement strip and the per-product offer panels both update on the customer site immediately. The badge ("BUNDLE", "-15%") shows up on product cards in real time.'),

  h2('Workflow C. A laptop sells out'),
  numbered([t('Open '), b('Admin > Products'), t('. Find the product and click the "–" stepper next to its stock count to drop it.')]),
  numbered('Once stock crosses 5, an automatic alert lands in the admin bell: "Low stock: {product} (N left)".'),
  numbered('At 0, the alert reads "Out of stock: {product}" and the product card on the customer site grays out with an "Out of stock" overlay.'),
  numbered('When you restock above 5 (using the same stepper, or by editing the product), the low-stock notification is automatically marked read.'),
  numbered('Dropping the same product below 5 again later will fire a fresh notification — there’s no permanent suppression.'),

  h2('Workflow D. Customer forgets their password'),
  numbered('Customer clicks "Forgot password?" on /login.'),
  numbered('Customer enters their email and clicks "Send reset link →".'),
  numbered('Page shows: "If an account exists for {email}, a reset link has been sent." (Same copy regardless of whether the email is registered — this prevents email enumeration.)'),
  numbered('In development the same screen also shows an amber "DEV SHORTCUT" card with the actual reset URL because no SMTP server is wired up; the email is written to storage/logs/laravel.log instead.'),
  numbered('Customer clicks the reset URL, sees /reset-password with their email pre-filled, sets a new password, and is auto-signed in.'),

  h2('Workflow E. Moderating a new review'),
  numbered([t('A "New 5-star review from Jane" notification arrives in the admin bell. Click it → lands on '), b('Admin > Reviews'), t(' with the Pending tab open.')]),
  numbered('Read the comment. If it looks legitimate, click "Approve". The review appears on the product page within seconds. The admin notification clears automatically.'),
  numbered('If it’s spam or abusive, click "Delete" instead. The customer is not separately notified.'),
  numbered('A customer can only review a given product once — a second attempt by the same email is rejected with a 422 error. This is enforced server-side.'),
];

/* ===== Section 6: Notifications ===== */
const sec6 = [
  h1('6. Notifications system'),
  p('Every visible badge in the platform is backed by a single notification record. There are two scopes:'),
  bullet([b('Admin notifications'), t(' — things you need to know about (new enquiries, new reviews, low stock).')]),
  bullet([b('Customer notifications'), t(' — things your customer needs to know about (enquiry replies, status changes).')]),
  p('They are stored in the same table but tagged with an owner so they never cross over. Customer notifications are invisible to admins and vice-versa.'),

  h2('Notification types'),
  spacer(120),
  makeTable(
    ['Type', 'Scope', 'When fired', 'Auto-cleared when...'],
    [
      ['enquiry', 'Admin', 'A customer submits a new enquiry (general or product-scoped).', 'Admin opens it and either replies or changes status, or deletes the enquiry.'],
      ['review', 'Admin', 'A customer submits a new review (always lands in moderation).', 'Admin approves or deletes the review.'],
      ['low_stock', 'Admin', 'A product’s stock crosses to ≤ 5 from above.', 'Admin restocks the product above 5, or the product is deleted. Deduped: only one open alert per product at a time.'],
      ['enquiry_reply', 'Customer', 'Admin saves a reply on the customer’s enquiry, or changes its status.', 'Customer clicks the notification (mark-read happens on click).'],
    ],
    [1600, 1200, 3000, 3560]
  ),
  spacer(120),
  callout(
    'The pulsing badge',
    'When unread > 0 the bell badge has a soft pulsing halo. The badge polls every 30 seconds so new notifications appear without a refresh. The same code powers both the admin and the customer bells.'
  ),
];

/* ===== Section 7: Security ===== */
const sec7 = [
  h1('7. Security & policies'),
  p('A handful of guardrails are baked into the platform. None of them block normal use; they are there to prevent abuse.'),

  h2('Rate limits'),
  spacer(120),
  makeTable(
    ['Endpoint', 'Limit', 'Why'],
    [
      ['POST /auth/login', '5 per minute per IP', 'Discourages credential stuffing.'],
      ['POST /auth/register', '3 per minute per IP', 'Discourages signup spam.'],
      ['POST /auth/forgot-password', '3 per minute per IP', 'Discourages email harvesting and abuse of the reset link.'],
      ['POST /auth/reset-password', '5 per minute per IP', 'Discourages brute-forcing the reset token.'],
      ['POST /reviews and /enquiries', '10 per minute per signed-in user', 'Stops a single customer flooding submissions.'],
    ],
    [2700, 2200, 4460]
  ),
  spacer(120),
  p('Past the limit, the server returns HTTP 429 and the frontend shows "Too many attempts. Please wait a minute."'),

  h2('Self-protection'),
  bullet('An admin cannot deactivate their own account from the Customers page.'),
  bullet('An admin cannot delete their own account.'),
  bullet('An admin cannot demote themselves from ‘admin’ role.'),
  bullet('These checks run server-side and return a 422 validation error if attempted via the API directly.'),

  h2('Review uniqueness'),
  p('Each customer can submit one review per product. A second attempt by the same email is rejected with the message "You have already reviewed this product." Deleting the original frees that slot.'),

  h2('Anti-enumeration on forgot password'),
  p('The /auth/forgot-password endpoint always returns HTTP 200 with the same generic message ("If an account exists for {email}, a reset link has been sent.") regardless of whether the email is registered. This prevents the endpoint from being used as an email-existence oracle.'),
];

/* ===== Section 8: Brand assets ===== */
const sec8 = [
  h1('8. Brand assets'),
  p('All visual and contact assets the platform uses, in one place.'),

  h2('Logo and favicons'),
  spacer(120),
  makeTable(
    ['Asset', 'File', 'Used on'],
    [
      ['Main logo (square JPG)', 'customer-site/public/logo.jpg and admin-panel/public/logo.jpg', 'Customer Navbar + Footer, admin sidebar + login screen.'],
      ['Favicon 16x16 (rounded PNG)', 'public/favicon-16.png in both apps', 'Browser tab icon at small sizes.'],
      ['Favicon 32x32 (rounded PNG)', 'public/favicon-32.png in both apps', 'Browser tab icon at retina sizes.'],
      ['Apple touch icon (rounded PNG)', 'public/favicon-180.png in both apps', 'iOS home-screen shortcut.'],
      ['Logo round 512px', 'public/logo-round.png in both apps', 'Reserved for higher-res needs (PWA icon, social share preview).'],
    ],
    [2400, 3600, 3360]
  ),
  spacer(160),

  h2('Business details'),
  makeTable(
    ['Field', 'Value'],
    [
      ['Brand name', 'Fluro Tech'],
      ['Address', 'Melpuram Junction, Near Bharath Petroleum Bunk, Pacode (P.O), Kanniyakumari District, Tamil Nadu – 629168'],
      ['Email', 'Flurotech46@gmail.com'],
      ['Phone', '+91 96774 09009'],
      ['Instagram', '@fluro_tech_  (https://www.instagram.com/fluro_tech_)'],
    ],
    [2400, 6960]
  ),
  spacer(160),

  h2('Colour palette'),
  p('The fluorescent green palette is defined in tailwind.config.js for both apps. Use these where the brand should show:'),
  spacer(120),
  makeTable(
    ['Token', 'Hex', 'Typical use'],
    [
      ['brand-50', '#ECFFF5', 'Subtle hover tint, banner backgrounds.'],
      ['brand-100', '#D2FFE5', 'Light pill backgrounds.'],
      ['brand-400', '#4ADE80', 'Lighter gradient stop.'],
      ['brand-500', '#22E36B', 'Primary fluorescent green.'],
      ['brand-600', '#16C95A', 'Buttons, links, primary actions.'],
      ['brand-700', '#10A648', 'Pressed / hover states, body emphasis.'],
    ],
    [2200, 1800, 5360]
  ),
];

/* ===== Section 9: Test accounts ===== */
const sec9 = [
  h1('9. Test accounts'),
  p('Three accounts are pre-seeded for manual testing. Change passwords before going to production.'),
  spacer(120),
  makeTable(
    ['Role', 'Email', 'Password', 'Notes'],
    [
      ['Admin', 'admin@example.com', 'password', 'Default admin. Has full access to the admin panel. Cannot be deactivated, demoted, or deleted from the UI.'],
      ['Customer', 'browser.tester@example.com', 'secret123', 'Test customer used during browser walkthroughs. Has at least one enquiry and a reply.'],
      ['Customer', 'p1test2@example.com', 'secret123', 'Another test customer with both an enquiry and a review on record.'],
    ],
    [1200, 3000, 1700, 3460]
  ),
];

/* ===== Section 10: Troubleshooting ===== */
const sec10 = [
  h1('10. Quick troubleshooting'),
  p('If something does not load, walk through these checks in order.'),

  h2('The customer site does not load'),
  numbered('Confirm the URL: http://localhost:5173 (note: localhost, not 127.0.0.1, for cookies / CORS).'),
  numbered('Check the terminal window running the customer-site dev server. Look for "VITE ready" — if you see compile errors, fix them or revert the last change.'),
  numbered('Make sure the backend is up at http://127.0.0.1:8000 — the customer site fetches from it. Visiting /api/products in a browser should return JSON, not an error page.'),

  h2('The admin panel does not load'),
  numbered('Confirm the URL: http://localhost:5174.'),
  numbered('Look at its Vite terminal for compile errors.'),
  numbered('If the page loads but you cannot sign in, make sure you are using admin@example.com / password and that the backend is up. A 401 means the credentials are wrong; a network error means the backend is down.'),

  h2('Emails are not arriving'),
  p('In the current development setup, password-reset emails are not sent over SMTP. They are written to a log file instead:'),
  bullet('Open backend/storage/logs/laravel.log in any text editor.'),
  bullet('The most recent entries include the full reset link, looking like http://localhost:5173/reset-password?token=...&email=...'),
  bullet('Copy and paste that URL into the browser to complete the reset, or use the amber DEV SHORTCUT card shown on the /forgot-password screen.'),
  p('When you are ready to send real email, edit backend/.env: change MAIL_MAILER from "log" to "smtp" and fill in MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD with your provider’s credentials.'),

  h2('An admin notification will not clear'),
  p('Admin notifications are auto-marked-read when the underlying entity changes — admin replies to the enquiry, approves the review, or restocks the product. If a notification is sticking around:'),
  bullet('Open the full /notifications page (Overview > Notifications).'),
  bullet('Hover the row and click "Mark read" or "Delete".'),
  bullet('"Mark all read" at the top of the bell dropdown will clear the badge in one click.'),

  h2('A customer says they cannot reset their password'),
  numbered('Confirm they are using the latest reset link — each new request invalidates the previous one.'),
  numbered('Confirm the email matches an existing customer (check Customers page).'),
  numbered('If they hit the rate limit (3 per minute per IP), they need to wait 60 seconds before trying again.'),
  numbered('In dev mode, you can also use the DEV SHORTCUT shown on the /forgot-password screen, or copy the link from storage/logs/laravel.log.'),
];

/* ===== Closing ===== */
const closing = [
  spacer(400),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    children: [new TextRun({ text: '— End of handbook —', italics: true, color: COLOR.muted, size: 22 })],
    spacing: { before: 400 },
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    children: [new TextRun({
      text: 'Questions? Update this document and re-share with your team.',
      italics: true, color: COLOR.muted, size: 20,
    })],
  }),
];

// ---------- Document ----------
const doc = new Document({
  creator: 'Fluro Tech',
  title: 'Fluro Tech — Owner’s Handbook',
  styles: {
    default: {
      document: { run: { font: 'Calibri', size: 22 } }, // 11pt body
    },
    paragraphStyles: [
      {
        id: 'Heading1',
        name: 'Heading 1',
        basedOn: 'Normal',
        next: 'Normal',
        quickFormat: true,
        run: { size: 36, bold: true, font: 'Calibri', color: COLOR.brand },
        paragraph: { spacing: { before: 360, after: 240 }, outlineLevel: 0 },
      },
      {
        id: 'Heading2',
        name: 'Heading 2',
        basedOn: 'Normal',
        next: 'Normal',
        quickFormat: true,
        run: { size: 28, bold: true, font: 'Calibri', color: COLOR.brandDark },
        paragraph: { spacing: { before: 280, after: 160 }, outlineLevel: 1 },
      },
      {
        id: 'Heading3',
        name: 'Heading 3',
        basedOn: 'Normal',
        next: 'Normal',
        quickFormat: true,
        run: { size: 24, bold: true, font: 'Calibri', color: COLOR.ink },
        paragraph: { spacing: { before: 200, after: 120 }, outlineLevel: 2 },
      },
    ],
  },
  numbering: {
    config: [
      {
        reference: 'bullets',
        levels: [
          {
            level: 0,
            format: LevelFormat.BULLET,
            text: '•',
            alignment: AlignmentType.LEFT,
            style: { paragraph: { indent: { left: 720, hanging: 360 } } },
          },
        ],
      },
      {
        reference: 'numbered',
        levels: [
          {
            level: 0,
            format: LevelFormat.DECIMAL,
            text: '%1.',
            alignment: AlignmentType.LEFT,
            style: { paragraph: { indent: { left: 720, hanging: 360 } } },
          },
        ],
      },
    ],
  },
  sections: [{
    properties: {
      page: {
        size: { width: PAGE_W, height: PAGE_H },
        margin: { top: MARGIN, right: MARGIN, bottom: MARGIN, left: MARGIN },
      },
    },
    headers: {
      default: new Header({
        children: [new Paragraph({
          alignment: AlignmentType.RIGHT,
          children: [new TextRun({
            text: 'Fluro Tech — Owner’s Handbook',
            size: 18, color: COLOR.muted,
          })],
        })],
      }),
    },
    footers: {
      default: new Footer({
        children: [new Paragraph({
          alignment: AlignmentType.CENTER,
          children: [
            new TextRun({ text: 'Page ', size: 18, color: COLOR.muted }),
            new TextRun({ children: [PageNumber.CURRENT], size: 18, color: COLOR.muted }),
            new TextRun({ text: ' of ', size: 18, color: COLOR.muted }),
            new TextRun({ children: [PageNumber.TOTAL_PAGES], size: 18, color: COLOR.muted }),
          ],
        })],
      }),
    },
    children: [
      ...titlePage,
      ...tocPage,
      ...sec1,
      ...sec2,
      ...sec3,
      ...sec4,
      ...sec5,
      ...sec6,
      ...sec7,
      ...sec8,
      ...sec9,
      ...sec10,
      ...closing,
    ],
  }],
});

const outPath = path.join(__dirname, 'Fluro-Tech-Owner-Handbook.docx');
Packer.toBuffer(doc).then((buffer) => {
  fs.writeFileSync(outPath, buffer);
  console.log('Wrote:', outPath, '(' + buffer.length.toLocaleString() + ' bytes)');
}).catch((err) => {
  console.error('FAILED:', err);
  process.exit(1);
});
