/******************** CLOCK *************************/
console.log('Java.js loaded');
function updateDateTime() {
  const el = document.getElementById('date-time');
  if (el) el.textContent = new Date().toLocaleString();
}
updateDateTime();
setInterval(updateDateTime, 1000);

/******************** CONTACT PAGE (regex-first) *************************/
function validateContactComment() {
  const comment = (document.getElementById('comment')?.value || '').trim();
  if (comment.length < 10) {
    alert('Comment must be at least 10 characters.');
    return false;
  }
  return true;
}

/******************** APPEARANCE CONTROLS *************************/
document.getElementById("bgColorControl")?.addEventListener("input", function() {
  document.body.style.backgroundColor = this.value;
});
document.getElementById("fontSizeControl")?.addEventListener("input", function() {
  const s = document.querySelector(".section");
  if (s) s.style.fontSize = this.value + "px";
});

/* ================================================================
   FLIGHTS — SHARED CONSTANTS + HELPERS
================================================================= */
const API_BASE = 'http://localhost:3000'; // <— your server origin

// City list via regex
const txCaCityRx = new RegExp(
  '^\\s*(?:' + [
    // Texas
    'Austin','Dallas','Houston','San Antonio','El Paso','Fort Worth','Arlington','Plano','Irving','Denton',
    'Corpus Christi','Lubbock','Garland','McKinney','Frisco','Amarillo','Grand Prairie','Brownsville',
    // California
    'Los Angeles','San Diego','San Jose','San Francisco','Fresno','Sacramento','Long Beach','Oakland',
    'Bakersfield','Anaheim','Riverside','Stockton','Irvine','Santa Ana','Chula Vista','Fremont','San Bernardino'
  ].join('|') + ')\\s*$', 'i'
);
const dateRx  = /^\d{4}-\d{2}-\d{2}$/;
const minDate = new Date('2024-09-01');
const maxDate = new Date('2024-12-01');
const paxRx   = /^[0-4]$/;

const FLIGHTS_DB_KEY = 'flights_db_manual_v1';
const FLIGHTS_DB_META_KEY = 'flights_db_manual_meta';

// Local cache helpers
function getFlightsDb() {
  return JSON.parse(localStorage.getItem(FLIGHTS_DB_KEY) || '[]');
}
function saveFlightsDb(db) {
  localStorage.setItem(FLIGHTS_DB_KEY, JSON.stringify(db));
}

// Normalize flight objects (handle departureDate vs departDate, etc.)
function normalizeFlight(f) {
  const copy = { ...f };
  if (copy.departureDate && !copy.departDate) copy.departDate = copy.departureDate;
  if (copy.departureTime && !copy.departTime) copy.departTime = copy.departureTime;
  if (copy.arrivalDate  && !copy.arrivalDate) copy.arrivalDate = copy.arrivalDate;
  if (copy.arrivalTime  && !copy.arrivalTime) copy.arrivalTime = copy.arrivalTime;
  return copy;
}
function normalizeFlights(arr) {
  return (Array.isArray(arr) ? arr : []).map(normalizeFlight);
}

// Load flights from backend server (now uses API_BASE)
// Load flights from flights.json into localStorage (no server needed)
async function ensureFlightsDbFromFile(force = false) {
  const meta = JSON.parse(localStorage.getItem(FLIGHTS_DB_META_KEY) || 'null');
  const existing = getFlightsDb();

  if (
    !force &&
    Array.isArray(existing) &&
    existing.length &&
    meta?.source === 'file' &&
    meta?.version === 1
  ) {
    return; // already loaded
  }

  try {
    const res = await fetch('flights.json', { cache: 'no-store' });
    if (!res.ok) {
      console.error('Failed to load flights.json:', res.status, res.statusText);
      return;
    }
    const flightsRaw = await res.json();
    const flights = normalizeFlights(flightsRaw);
    if (!Array.isArray(flights)) {
      console.error('flights.json did not contain an array');
      return;
    }

    saveFlightsDb(flights);
    localStorage.setItem(
      FLIGHTS_DB_META_KEY,
      JSON.stringify({ source: 'file', version: 1, loadedAt: new Date().toISOString() })
    );
    console.log('Flights loaded from flights.json into localStorage:', flights.length);
  } catch (err) {
    console.error('Error loading flights.json:', err);
  }
}// Date helpers
function dateToYMD(d){ const p=n=>String(n).padStart(2,'0'); return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`; }
function ymdToDate(s){ const [y,m,d]=s.split('-').map(Number); return new Date(y,m-1,d); }

// Search helpers (exact + ±3 days if no exact)
function searchFlights(origin, destination, ymd, paxNeeded, opts = {}) {
  const db = getFlightsDb();
  const minYmd = opts.minYmd || null;

  const matches = db.filter(f =>
    (f.origin || '').toLowerCase() === origin.toLowerCase() &&
    (f.destination || '').toLowerCase() === destination.toLowerCase() &&
    f.departDate === ymd &&
    Number(f.availableSeats) >= paxNeeded
  );

  if (matches.length) return { exact: matches, alt: [] };

  const center = ymdToDate(ymd);
  if (isNaN(center)) return { exact: [], alt: [] };

  const alt = [];
  for (let offset=-3; offset<=3; offset++) {
    if (offset===0) continue;
    const dt = new Date(center); dt.setDate(center.getDate()+offset);
    const y = dateToYMD(dt);
    if (minYmd && y < minYmd) continue;

    const dayMatches = db.filter(f =>
      (f.origin || '').toLowerCase() === origin.toLowerCase() &&
      (f.destination || '').toLowerCase() === destination.toLowerCase() &&
      f.departDate === y &&
      Number(f.availableSeats) >= paxNeeded
    );
    if (dayMatches.length) alt.push({ date: y, flights: dayMatches });
  }
  return { exact: [], alt };
}

// Render helpers
function flightCardHTML(f) {
  const rowId = `${f.flightId}|${f.departDate}`;
  return `
    <div class="flightCard">
      <div><strong>Flight ID:</strong> ${f.flightId}</div>
      <div><strong>Origin:</strong> ${f.origin}</div>
      <div><strong>Destination:</strong> ${f.destination}</div>
      <div><strong>Departure date:</strong> ${f.departDate}</div>
      <div><strong>Arrival date:</strong> ${f.arrivalDate}</div>
      <div><strong>Departure time:</strong> ${f.departTime}</div>
      <div><strong>Arrival time:</strong> ${f.arrivalTime}</div>
      <div><strong>Number of available seats:</strong> ${f.availableSeats}</div>
      <div><strong>Price (Adult):</strong> $${f.price}</div>
      <button class="btn addFlight" data-id="${rowId}">Select</button>
    </div>
  `;
}
function renderFlights(listEl, flights) {
  listEl.innerHTML = flights.length ? flights.map(f => flightCardHTML(f)).join('') : '<p>No flights.</p>';
}
function renderAltFlights(listEl, alt) {
  if (!alt.length) {
    listEl.innerHTML += `<p>No alternate dates within ±3 days.</p>`;
    return;
  }
  listEl.innerHTML += `<h5>Also available within ±3 days:</h5>`;
  alt.sort((a,b)=>a.date.localeCompare(b.date));
  alt.forEach(group => {
    listEl.innerHTML += `<p><em>${group.date}</em></p>` +
      group.flights.map(f => flightCardHTML(f)).join('');
  });
}

// Validate flights form — must pass before any search results are shown
function validateFlightsForm() {
  const errorBox = document.getElementById('errorBox');
  const originEl = document.getElementById('origin');
  const destEl   = document.getElementById('destination');
  const departEl = document.getElementById('departDate');
  const returnEl = document.getElementById('returnDate');
  const adultEl  = document.getElementById('adultCount');
  const childEl  = document.getElementById('childCount');
  const infantEl = document.getElementById('infantCount');

  const trip   = document.querySelector('input[name="trip"]:checked')?.value || 'oneway';
  const origin = (originEl?.value || '').trim();
  const dest   = (destEl?.value   || '').trim();
  const depart = (departEl?.value || '').trim();
  const ret    = (returnEl?.value || '').trim();

  const a = (adultEl?.value || '').trim();
  const c = (childEl?.value || '').trim();
  const i = (infantEl?.value|| '').trim();

  function fail(msg){ if (errorBox) errorBox.textContent = msg; return { ok:false }; }
  if (errorBox) errorBox.textContent = '';

  if (!txCaCityRx.test(origin)) return fail('Origin must be a city in Texas or California.');
  if (!txCaCityRx.test(dest))   return fail('Destination must be a city in Texas or California.');
  if (origin.toLowerCase() === dest.toLowerCase()) return fail('Origin and destination must be different.');

  if (!dateRx.test(depart)) return fail('Departure date must be in YYYY-MM-DD format.');
  const dDate = new Date(depart);
  if (isNaN(dDate) || dDate < minDate || dDate > maxDate) return fail('Departure date must be between 2024-09-01 and 2024-12-01.');

  if (!paxRx.test(a)) return fail('Adults must be a number from 0 to 4.');
  if (!paxRx.test(c)) return fail('Children must be a number from 0 to 4.');
  if (!paxRx.test(i)) return fail('Infants must be a number from 0 to 4.');
  const total = Number(a)+Number(c)+Number(i);
  if (total < 1) return fail('At least one passenger is required.');

  if (trip === 'round') {
    if (!dateRx.test(ret)) return fail('Return date must be in YYYY-MM-DD format.');
    const rDate = new Date(ret);
    if (isNaN(rDate) || rDate < minDate || rDate > maxDate)
      return fail('Return date must be between 2024-09-01 and 2024-12-01.');
    if (rDate <= dDate) return fail('Return date must be after the departure date.');
  }

  return {
    ok: true,
    trip, origin, dest, depart, ret,
    paxTotal: Number(a)+Number(c)+Number(i),
    a: Number(a), c: Number(c), i: Number(i)
  };
}

/******************** FLIGHTS PAGE WIRING *************************/
document.addEventListener('DOMContentLoaded', () => {
  // Toggle return date based on trip radios (and initialize once)
  const tripRadios = document.querySelectorAll('input[name="trip"]');
  const returnWrap = document.getElementById('returnWrap');
  const toggleReturn = () => {
    const val = document.querySelector('input[name="trip"]:checked')?.value || 'oneway';
    if (returnWrap) {
      if (val === 'round') {
        returnWrap.style.display = '';
      } else {
        returnWrap.style.display = 'none';
        const re = document.getElementById('returnDate');
        if (re) re.value = '';
      }
    }
  };
  tripRadios.forEach(r => r.addEventListener('change', toggleReturn));
  toggleReturn(); // set initial

  (async () => {
    await ensureFlightsDbFromFile();

    const resultsWrap = document.getElementById('flightResults');
    const outList     = document.getElementById('outboundList');
    const inWrap      = document.getElementById('inboundWrap');
    const inList      = document.getElementById('inboundList');
    const addBothWrap = document.getElementById('addBothContainer');
    const addBothBtn  = document.getElementById('addRoundToCart');
    const resultBox   = document.getElementById('result');

    if (!resultsWrap || !outList) return; // not on flights page

    // Global context for selections
    window.currentContext   = { paxTotal: 0, trip: 'oneway', origin: '', dest: '', depart: '', ret: '' };
    window.selectedOutbound = null;
    window.selectedInbound  = null;

    // Search button
    const searchBtn = document.getElementById('searchBtn');
    searchBtn?.addEventListener('click', () => {
      const v = validateFlightsForm();
      if (!v.ok) {
        resultsWrap.style.display = 'none';
        if (resultBox) { resultBox.style.display = 'none'; resultBox.innerHTML = ''; }
        return;
      }

      const { trip, origin, dest, depart, ret, paxTotal, a, c, i } = v;
      window.currentContext = { paxTotal, trip, origin, dest, depart, ret };
      window.selectedOutbound = null;
      window.selectedInbound  = null;

      if (resultBox) {
        const lines = [
          `<strong>Trip type:</strong> ${trip === 'round' ? 'Round trip' : 'One-way'}`,
          `<strong>Origin:</strong> ${origin}`,
          `<strong>Destination:</strong> ${dest}`,
          `<strong>Departure:</strong> ${depart}`,
        ];
        if (trip === 'round') lines.push(`<strong>Return:</strong> ${ret}`);
        lines.push(`<strong>Passengers:</strong> Adults ${a}, Children ${c}, Infants ${i}`);
        resultBox.innerHTML = lines.join('<br>');
        resultBox.style.display = 'block';
      }

      const out = searchFlights(origin, dest, depart, paxTotal);
      outList.innerHTML = '';
      renderFlights(outList, out.exact);
      renderAltFlights(outList, out.alt);

      if (trip === 'round') {
        if (inWrap) inWrap.style.display = '';
        const back = searchFlights(dest, origin, ret, paxTotal, { minYmd: depart });
        if (inList) {
          inList.innerHTML = '';
          renderFlights(inList, back.exact);
          renderAltFlights(inList, back.alt);
        }
        if (addBothWrap) addBothWrap.style.display = 'none';
      } else {
        if (inWrap) inWrap.style.display = 'none';
        if (addBothWrap) addBothWrap.style.display = 'none';
      }

      resultsWrap.style.display = 'block';
    });

    // Add both to cart (round trip)
    addBothBtn?.addEventListener('click', () => {
      if (!(window.selectedOutbound && window.selectedInbound)) return;
      if (typeof window.addRoundToCart === 'function') {
        window.addRoundToCart(window.selectedOutbound, window.selectedInbound, window.currentContext);
      } else {
        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const getVal = id => Number((document.getElementById(id)?.value || '0').trim());
        cart.push({
          type: 'round',
          counts: { adults: getVal('adultCount'), children: getVal('childCount'), infants: getVal('infantCount') },
          outbound: window.selectedOutbound,
          inbound:  window.selectedInbound
        });
        localStorage.setItem('cart', JSON.stringify(cart));
      }
      alert('Departing + Returning flights added to cart!');
      if (addBothWrap) addBothWrap.style.display = 'none';
    });
  })();
});

/******************** SELECT BUTTON HANDLER (works for both lists) ******/
document.addEventListener('click', (e) => {
  if (!(e.target && e.target.classList.contains('addFlight'))) return;
  const id = e.target.getAttribute('data-id');        // "flightId|departDate"
  const [fid, fdate] = (id || '').split('|');
  const db = getFlightsDb();
  const f  = db.find(x => x.flightId === fid && x.departDate === fdate);
  if (!f) return;

  if (window.currentContext?.trip === 'round') {
    const card = e.target.closest('.flightCard');
    const isOutboundBtn = document.getElementById('outboundList')?.contains(card);
    if (isOutboundBtn) {
      window.selectedOutbound = f;
      e.target.textContent = 'Selected (departing)';
    } else {
      window.selectedInbound = f;
      e.target.textContent = 'Selected (returning)';
    }
    const addBothWrap = document.getElementById('addBothContainer');
    if (window.selectedOutbound && window.selectedInbound && addBothWrap) {
      addBothWrap.style.display = '';
    }
  } else {
    if (typeof window.addOneWayToCart === 'function') {
      window.addOneWayToCart(f, window.currentContext);
    } else {
      const cart = JSON.parse(localStorage.getItem('cart') || '[]');
      const getVal = id => Number((document.getElementById(id)?.value || '0').trim());
      cart.push({
        type: 'oneway',
        counts: { adults: getVal('adultCount'), children: getVal('childCount'), infants: getVal('infantCount') },
        outbound: f
      });
      localStorage.setItem('cart', JSON.stringify(cart));
    }
    alert('Flight added to cart!');
  }
});

/******************** CART HELPERS *************************/
function getCart(){ return JSON.parse(localStorage.getItem('cart') || '[]'); }
function setCart(v){ localStorage.setItem('cart', JSON.stringify(v)); }
function getVal(id){ const v = document.getElementById(id)?.value || '0'; return Number(v.trim()); }

function addOneWayToCart(flight, ctx) {
  const cart = getCart();
  cart.push({
    type: 'oneway',
    counts: { adults: getVal('adultCount'), children: getVal('childCount'), infants: getVal('infantCount') },
    outbound: flight
  });
  setCart(cart);
}

function addRoundToCart(outbound, inbound, ctx) {
  const cart = getCart();
  cart.push({
    type: 'round',
    counts: { adults: getVal('adultCount'), children: getVal('childCount'), infants: getVal('infantCount') },
    outbound, inbound
  });
  setCart(cart);
}

function updateLocalSeatsAfterBooking(resp, totalPax) {
  const db = getFlightsDb();
  if (!Array.isArray(db) || !Array.isArray(resp.bookings)) return;

  resp.bookings.forEach(b => {
    // match on flightId + departDate
    const idx = db.findIndex(f =>
      f.flightId === b.flight_id &&          // DB field: flight_id
      f.departDate === b.depart_date        // DB field: depart_date
    );

    if (idx !== -1) {
      const current = Number(db[idx].availableSeats || 0);
      let next = current - totalPax;
      if (next < 0) next = 0;
      db[idx].availableSeats = next;
    }
  });

  saveFlightsDb(db);
}

/******************** CART PAGE BOOTSTRAP *************************/
document.addEventListener('DOMContentLoaded', () => {
  (async () => {
    await ensureFlightsDbFromFile(); // make sure we have the latest flights

    const cartFlights = document.getElementById('cartFlights');
    const cartTotalEl = document.getElementById('cartTotal');
    const formWrap    = document.getElementById('passengerFormWrap');
    const formEl      = document.getElementById('passengerForm');
    const bookBtn     = document.getElementById('bookBtn');
    const bookMsg     = document.getElementById('bookMsg');

    if (!cartFlights || !cartTotalEl) return; // not on cart page

    const money = n => '$' + Number(n).toFixed(2);
    let workingItem = null;

    function labeledFlightHTML(f, title = null) {
      return `
        <div class="flightCard">
          ${title ? `<div style="margin-bottom:6px;"><strong>${title}</strong></div>` : ""}
          <div><strong>Flight ID:</strong> ${f.flightId}</div>
          <div><strong>Origin:</strong> ${f.origin}</div>
          <div><strong>Destination:</strong> ${f.destination}</div>
          <div><strong>Departure date:</strong> ${f.departDate}</div>
          <div><strong>Arrival date:</strong> ${f.arrivalDate}</div>
          <div><strong>Departure time:</strong> ${f.departTime}</div>
          <div><strong>Arrival time:</strong> ${f.arrivalTime}</div>
        </div>
      `;
    }

    function renderCart() {
      const cart = getCart();
      if (!cart.length) {
        cartFlights.innerHTML = '<p>No flights bookings found.</p>';
        cartTotalEl.textContent = '$0.00';
        if (formWrap) formWrap.style.display = 'none';
        return;
      }

      // use the last added cart item as the "active" one
      workingItem = cart[cart.length - 1];
      const counts = workingItem.counts || { adults: 0, children: 0, infants: 0 };
      const legs =
        (workingItem.type === 'round')
          ? [workingItem.outbound, workingItem.inbound]
          : [workingItem.outbound];

      const legHtml = legs.map((f, i) =>
        labeledFlightHTML(
          f,
          workingItem.type === 'round'
            ? (i === 0 ? 'Departing flight' : 'Returning flight')
            : null
        )
      ).join('');

      const adultPrice  = legs.reduce((s, f) => s + Number(f.price || 0), 0);
      const childPrice  = adultPrice * 0.70;
      const infantPrice = adultPrice * 0.10;
      const total =
        (counts.adults  | 0) * adultPrice +
        (counts.children| 0) * childPrice +
        (counts.infants | 0) * infantPrice;

      cartFlights.innerHTML = `
        <p><strong>Trip:</strong> ${workingItem.type === 'round' ? 'Round trip' : 'One-way'}</p>
        ${legHtml}
        <p><strong>Passengers:</strong> Adults ${counts.adults}, Children ${counts.children}, Infants ${counts.infants}</p>
        <p><em>Fare rule:</em> Child = 70% of adult, Infant = 10% of adult.</p>
      `;
      cartTotalEl.textContent = money(total);

      const totalPax =
        (counts.adults  | 0) +
        (counts.children| 0) +
        (counts.infants | 0);

      if (formWrap && formEl) {
        if (totalPax > 0) {
          formEl.innerHTML = '';
          for (let k = 1; k <= totalPax; k++) {
            formEl.innerHTML += `
              <fieldset style="margin-bottom:10px;">
                <legend>Passenger ${k}</legend>
                <label>First name <input required name="fn${k}" type="text"></label>
                <label>Last name <input required name="ln${k}" type="text"></label>
                <label>Date of Birth <input required name="dob${k}" type="date"></label>
                <label>SSN <input required name="ssn${k}" type="text" placeholder="XXX-XX-XXXX"></label>
              </fieldset>
            `;
          }
          formWrap.style.display = '';
        } else {
          formWrap.style.display = 'none';
        }
      }
    }

    renderCart();

    function getOrCreateUserId() {
      let uid = localStorage.getItem('user_id');
      if (!uid) {
        uid = 'U-' + Math.random().toString(36).slice(2, 8).toUpperCase();
        localStorage.setItem('user_id', uid);
      }
      return uid;
    }

    function newBookingNumber() {
      const d = new Date();
      const pad = n => String(n).padStart(2, '0');
      const tag = `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}`;
      return 'BK-' + tag + '-' + Math.random().toString(36).slice(2, 6).toUpperCase();
    }

    // ===== BOOK BUTTON: send booking to book_flight.php =====
    bookBtn?.addEventListener('click', (e) => {
      e.preventDefault();
      if (!workingItem || !formEl) return;

      const data   = new FormData(formEl);
      const counts = workingItem.counts || { adults: 0, children: 0, infants: 0 };
      const totalPax =
        (counts.adults  | 0) +
        (counts.children| 0) +
        (counts.infants | 0);

      const pax = [];
      for (let k = 1; k <= totalPax; k++) {
        const fn  = (data.get('fn'  + k) || '').toString().trim();
        const ln  = (data.get('ln'  + k) || '').toString().trim();
        const dob = (data.get('dob' + k) || '').toString().trim();
        const ssn = (data.get('ssn' + k) || '').toString().trim();

        if (!fn || !ln || !dob || !ssn) {
          alert('Please fill all passenger fields.');
          return;
        }

        // assign category: first adults, then children, then infants
        let category;
        if (k <= (counts.adults | 0)) {
          category = 'adult';
        } else if (k <= (counts.adults | 0) + (counts.children | 0)) {
          category = 'child';
        } else {
          category = 'infant';
        }

        pax.push({ ssn, firstName: fn, lastName: ln, dob, category });
      }

      const legs =
        (workingItem.type === 'round')
          ? [workingItem.outbound, workingItem.inbound]
          : [workingItem.outbound];

      $.ajax({
        url: 'book_flight.php',
        method: 'POST',
        dataType: 'json',
        data: {
          type:       workingItem.type,
          legs:       JSON.stringify(legs.map(f => ({
            flightId:   f.flightId,
            departDate: f.departDate
          }))),
          passengers: JSON.stringify(pax),
          counts:     JSON.stringify(counts)
        },
       success: function (resp) {
          console.log('book_flight.php response:', resp);
          console.log('Tickets array:', resp.tickets);

          // If the server returns ok:false, treat it as a failure
          if (!resp || resp.ok === false) {
            alert('Booking failed: ' + (resp && resp.error ? resp.error : 'Unknown error'));
            return;
          }

          // remove last item from local cart
          const cart = getCart();
          cart.pop();
          setCart(cart);
          renderCart();

updateLocalSeatsAfterBooking(resp, totalPax);
          // ====== SHOW DETAIL ON PAGE (bookMsg) ======
          if (bookMsg) {
            let html = `
              <h3>Booking completed and stored in database.</h3>
              <h4>Booked Flights</h4>
            `;

            if (Array.isArray(resp.bookings) && resp.bookings.length) {
              resp.bookings.forEach(b => {
                html += `
                  <div class="flightCard">
                    <p><strong>Flight Booking ID:</strong> ${b.flight_booking_id}</p>
                    <p><strong>Flight ID:</strong> ${b.flight_id}</p>
                    <p><strong>Route:</strong> ${b.origin} → ${b.destination}</p>
                    <p><strong>Departure:</strong> ${b.depart_date} ${b.depart_time}</p>
                    <p><strong>Arrival:</strong> ${b.arrival_date} ${b.arrival_time}</p>
                    <p><strong>Total price (all passengers):</strong>
                       $${Number(b.total_price).toFixed(2)}</p>
                  </div>
                `;
              });
            } else {
              html += `<p>(No booking records returned from server.)</p>`;
            }

            html += '<h4>Tickets</h4>';
            if (Array.isArray(resp.tickets) && resp.tickets.length) {
              resp.tickets.forEach(t => {
                html += `
                  <div class="flightCard">
                    <p><strong>Ticket ID:</strong> ${t.ticket_id}</p>
                    <p><strong>Flight Booking ID:</strong> ${t.flight_booking_id}</p>
                    <p><strong>Passenger:</strong> ${t.first_name} ${t.last_name}
                       (SSN: ${t.ssn})</p>
                    <p><strong>Date of Birth:</strong> ${t.dob}</p>
                    <p><strong>Ticket price:</strong>
                       $${Number(t.price).toFixed(2)}</p>
                  </div>
                `;
              });
            } else {
              html += `<p>(No ticket records returned from server.)</p>`;
            }

            bookMsg.innerHTML = html;
            bookMsg.style.display = 'block';
            bookMsg.scrollIntoView({ behavior: 'smooth' });
          }

          // ====== BUILD DETAILED ALERT TEXT ======
          const lines = [];
          lines.push('Booking completed and stored in database.');
          lines.push(''); // blank line
          lines.push('Booked flights:');

          if (Array.isArray(resp.bookings) && resp.bookings.length) {
            resp.bookings.forEach((b, idx) => {
              lines.push('');
              lines.push(`Flight ${idx + 1}:`);
              lines.push(`  flight-booking-id: ${b.flight_booking_id}`);
              lines.push(`  flight-id: ${b.flight_id}`);
              lines.push(`  origin: ${b.origin}`);
              lines.push(`  destination: ${b.destination}`);
              lines.push(`  departure date: ${b.depart_date}`);
              lines.push(`  arrival date: ${b.arrival_date}`);
              lines.push(`  departure time: ${b.depart_time}`);
              lines.push(`  arrival time: ${b.arrival_time}`);
              lines.push(`  total price for all passengers: $${Number(b.total_price).toFixed(2)}`);
            });
          } else {
            lines.push('  (no booking records returned)');
          }

          lines.push('');
          lines.push('Ticket information:');

          if (Array.isArray(resp.tickets) && resp.tickets.length) {
            resp.tickets.forEach((t, idx) => {
              lines.push('');
              lines.push(`Ticket ${idx + 1}:`);
              lines.push(`  ticket-id: ${t.ticket_id}`);
              lines.push(`  flight-booking-id: ${t.flight_booking_id}`);
              lines.push(`  SSN: ${t.ssn}`);
              lines.push(`  first name: ${t.first_name}`);
              lines.push(`  last name: ${t.last_name}`);
              lines.push(`  date of birth: ${t.dob}`);
              lines.push(`  price: $${Number(t.price).toFixed(2)}`);
            });
          } else {
            lines.push('  (no ticket records returned)');
          }

          alert(lines.join('\n'));
        },
        error: function (xhr, textStatus, errorThrown) {
          console.error('book_flight.php error:', textStatus, errorThrown, xhr.responseText);
          alert('Booking failed on server side.');
        }
      });
    });

  })();
});
/* ==================== STAYS PAGE LOGIC (no RegExp) ==================== */
const TX_CITIES = [
  "austin","dallas","houston","san antonio","el paso","fort worth","arlington","plano","irving","denton",
  "corpus christi","lubbock","garland","mckinney","frisco","amarillo","grand prairie","brownsville"
];
const CA_CITIES = [
  "los angeles","san diego","san jose","san francisco","fresno","sacramento","long beach","oakland",
  "bakersfield","anaheim","riverside","stockton","irvine","santa ana","chula vista","fremont","san bernardino"
];
const STAY_MIN = new Date("2024-09-01");
const STAY_MAX = new Date("2024-12-01");
function isEmpty(v){ return v === null || v === undefined || String(v).trim() === ""; }
function parseIntSafe(v){ const n = Number(v); return Number.isFinite(n) ? n : NaN; }
function isCityTXorCA(name){
  const n = String(name || "").trim().toLowerCase();
  return TX_CITIES.includes(n) || CA_CITIES.includes(n);
}
function inDateWindow(d){ return d instanceof Date && !isNaN(d) && d >= STAY_MIN && d <= STAY_MAX; }
function computeRooms(adults, children){
  const paying = Math.max(0, adults) + Math.max(0, children);
  return Math.max(1, Math.ceil(paying / 2));
}
document.addEventListener("DOMContentLoaded", function(){
  const btn = document.getElementById("staySearchBtn");
  if(!btn) return;

  const cityEl     = document.getElementById("stayCity");
  const inEl       = document.getElementById("checkIn");
  const outEl      = document.getElementById("checkOut");
  const adEl       = document.getElementById("stayAdults");
  const chEl       = document.getElementById("stayChildren");
  const infEl      = document.getElementById("stayInfants");
  const errBox     = document.getElementById("stayError");
  const summaryBox = document.getElementById("staySummary");

  btn.addEventListener("click", function(){
    errBox.textContent = "";
    summaryBox.style.display = "none";
    summaryBox.innerHTML = "";

    if (isEmpty(cityEl.value) || isEmpty(inEl.value) || isEmpty(outEl.value)) {
      errBox.textContent = "Please enter city, check-in, and check-out dates.";
      return;
    }
    if (!isCityTXorCA(cityEl.value)) { errBox.textContent = "City must be in Texas or California."; return; }

    const inDate  = new Date(inEl.value);
    const outDate = new Date(outEl.value);
    if (!inDateWindow(inDate) || !inDateWindow(outDate)) { errBox.textContent = "Dates must be between 2024-09-01 and 2024-12-01."; return; }
    if (!(outDate > inDate)) { errBox.textContent = "Check-out date must be after check-in date."; return; }

    const ad = parseIntSafe(adEl.value);
    const ch = parseIntSafe(chEl.value);
    const inf = parseIntSafe(infEl.value);
    if (!Number.isInteger(ad) || ad < 0 || !Number.isInteger(ch) || ch < 0 || !Number.isInteger(inf) || inf < 0) {
      errBox.textContent = "Guest counts must be whole numbers ≥ 0."; return;
    }
    if ((ad + ch + inf) < 1) { errBox.textContent = "Please specify at least one guest."; return; }

    const rooms = computeRooms(ad, ch);
    summaryBox.innerHTML = [
    `<strong>City:</strong> ${cityEl.value.trim()}`,
    `<strong>Check-in:</strong> ${inEl.value}`,
    `<strong>Check-out:</strong> ${outEl.value}`,
    `<strong>Guests:</strong> Adults ${ad}, Children ${ch}, Infants ${inf}`,
    `<strong>Rooms needed:</strong> ${rooms}`
    ].join("<br>");
    summaryBox.style.display = "block";

    if(errBox.textContent == ""){
      fetch("hotels.xml")
      .then(res => res.text())
      .then(str => (new window.DOMParser()).parseFromString(str, "text/xml"))
      .then(xml => {
        const hotels = Array.from(xml.getElementsByTagName("hotel"));
        const city = cityEl.value.trim();
  
        const matchingHotels = hotels.filter(h => {
          const hCity = h.getElementsByTagName("city")[0].textContent.trim().toLowerCase();
          if (hCity !== city.toLowerCase()) return false;
  
          const availableDate = new Date(h.getElementsByTagName("date")[0].textContent);
          const availRooms = parseInt(h.getElementsByTagName("availableRooms")[0].textContent);
  
          // Only show hotels where the available date overlaps the user’s stay window
          // and where there are enough rooms.
          const dateOK = (availableDate >= inDate && availableDate < outDate);
          const roomOK = availRooms >= rooms;
  
          return dateOK && roomOK;
        });
  
        // Display results
        const resultBox  = document.getElementById("stayResults");
        resultBox.style.display = "none";
  
        if (matchingHotels.length === 0) {
          errBox.textContent = "No available hotels match your city, dates, or room needs.";
          return;
        }
  
        let html = `<h3>Available Hotels in ${city}</h3>`;
        matchingHotels.forEach(h => {
          const id = h.getElementsByTagName("hotelId")[0].textContent;
          const name = h.getElementsByTagName("hotelName")[0].textContent;
          const price = h.getElementsByTagName("pricePerNight")[0].textContent;
          const avail = h.getElementsByTagName("availableRooms")[0].textContent;
  
          html += `
            <div class="hotelCard">
              <p><strong>${name}</strong> (ID: ${id})<br>
              Price: $${price}/night<br>
              Available rooms: ${avail}<br>
              Rooms needed: ${rooms}</p>
              <button class="addHotelBtn btn" 
                      data-id="${id}" 
                      data-name="${name}" 
                      data-city="${city}" 
                      data-price="${price}" 
                      data-rooms="${rooms}"
                      data-avail="${avail}">
                Add to Cart
              </button>
            </div>`;
        });
  
        document.getElementById("stayList").innerHTML = html;
        resultBox.style.display = "block";
  
        // Hook up Add-to-Cart
        document.querySelectorAll(".addHotelBtn").forEach(btn => {
          btn.addEventListener("click", e => {
            const d = e.target.dataset;

            // compute number of nights between check-in and check-out
            const inDate = new Date(inEl.value);
            const outDate = new Date(outEl.value);
            const nights = Math.round((outDate - inDate) / (1000 * 60 * 60 * 24));

            // pricePerNight field here represents the price for one room for the whole stay (per-night * nights)
            const pricePerRoomForStay = parseFloat(d.price) * (Number.isFinite(nights) && nights > 0 ? nights : 1);

            const pricePerNight = parseFloat(d.price);
            const roomsCount = parseInt(d.rooms, 10);
            const nightsCount = Number.isFinite(nights) && nights > 0 ? nights : 1;
            const booking = {
              userId: Date.now(),
              bookingNumber: "H" + Math.floor(Math.random() * 100000),
              hotelId: d.id,
              hotelName: d.name,
              city: d.city,
              checkIn: inEl.value,
              checkOut: outEl.value,
              adults: ad,
              children: ch,
              infants: inf,
              rooms: roomsCount,
              pricePerNight: pricePerNight, // price for one room for one night
              totalPrice: pricePerNight * nightsCount * roomsCount,
              availableRooms: parseInt(d.avail, 10)
            };

            // Preserve existing hotel cart items instead of overwriting
            const existing = JSON.parse(localStorage.getItem("hotelCart") || "[]");
            existing.push(booking);
            localStorage.setItem("hotelCart", JSON.stringify(existing));

            alert(`${d.rooms} rooms in ${d.name} added to cart!`);
            resultBox.style.display = "none";

          });
        });
      })
      .catch(err => {
        console.error("Error reading hotel XML:", err);
        document.getElementById("stayError").textContent = "Error loading hotel data.";
      });
    }

  });
});
// ========== HOTEL CART DISPLAY (HW3 - Part 5) ==========
document.addEventListener("DOMContentLoaded", function() {
  const hotelCartData = localStorage.getItem("hotelCart");
  const cartHotelsDiv = document.getElementById("cartHotels");
  const totalSpan = document.getElementById("cartTotalHotels");

 if (!cartHotelsDiv) return; // not on cart page
  const hotelCart = JSON.parse(hotelCartData || "[]");
  if (!Array.isArray(hotelCart) || hotelCart.length === 0) {
  cartHotelsDiv.innerHTML = "<p>No hotel bookings found.</p>";
  if (totalSpan) totalSpan.textContent = "$0.00";
  const bookHotelsBtn = document.getElementById("bookHotelsBtn");
  if (bookHotelsBtn) bookHotelsBtn.style.display = "none";
  return;
}

  let total = 0;
  let html = "<h3>Hotel Bookings</h3>";

  hotelCart.forEach(h => {
    total += h.totalPrice;

    html += `
      <div class="hotelCartItem" style="border:1px solid #ccc; padding:10px; margin:10px 0;">
        <p><strong>${h.hotelName}</strong> (ID: ${h.hotelId})</p>
        <p>City: ${h.city}</p>
        <p>Check-in: ${h.checkIn}</p>
        <p>Check-out: ${h.checkOut}</p>
        <p>Guests: ${h.adults} Adults, ${h.children} Children, ${h.infants} Infants</p>
        <p>Rooms: ${h.rooms}</p>
        <p>Price per night: $${h.pricePerNight.toFixed(2)}</p>
        <p><strong>Total Price: $${h.totalPrice.toFixed(2)}</strong></p>
        <p>Booking #: ${h.bookingNumber}</p>
        <p>User ID: ${h.userId}</p>
      </div>
    `;
  });

  cartHotelsDiv.innerHTML += html;
  totalSpan.textContent = `$${total.toFixed(2)}`;

  const bookHotelsBtn = document.getElementById("bookHotelsBtn");
  bookHotelsBtn.style.display = "block";
  const msgBox = document.getElementById("bookHotelsMsg");
  if (!bookHotelsBtn) return;

  bookHotelsBtn.addEventListener("click", function() {
  const hotelCartData = localStorage.getItem("hotelCart");
  if (!hotelCartData) {
    msgBox.textContent = "No hotels to book!";
    msgBox.style.display = "block";
    return;
  }

  const hotelCart = JSON.parse(hotelCartData);
  if (!Array.isArray(hotelCart) || hotelCart.length === 0) {
    msgBox.textContent = "No hotels to book!";
    msgBox.style.display = "block";
    return;
  }

  // Generate JSON file of booked hotels
  const jsonStr = JSON.stringify(hotelCart, null, 2);
  const blob = new Blob([jsonStr], { type: "application/json" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = "hotel_bookings.json";
  a.click();
  URL.revokeObjectURL(a.href);

  msgBox.textContent = "Hotel booking JSON file generated successfully!";
  msgBox.style.display = "block";
  cartHotelsDiv.innerHTML = "";
  totalSpan.textContent = "$0.00";
  bookHotelsBtn.style.display = "none";
  localStorage.removeItem("hotelCart");

  // Send updates sequentially to avoid concurrent-write races on hotels.xml
  (async () => {
    for (const h of hotelCart) {
      const updatedAvailableRooms = Math.max(0, h.availableRooms - h.rooms);
      try {
        const resp = await fetch('/update_hotel', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ hotelId: h.hotelId, newAvailableRooms: updatedAvailableRooms })
        });
        const text = await resp.text();
        console.log(`Updated ${h.hotelName}: ${text}`);
      } catch (err) {
        console.error('Failed to update', h.hotelId, err);
      }
    }
  })();


});

});


/* ================= PASSENGERS PANEL (robust init) ================= */
function initPassengersPanel() {
  const paxBtn   = document.getElementById('paxBtn');
  const paxPanel = document.getElementById('paxPanel');
  if (!paxBtn || !paxPanel) return;

  paxPanel.style.display = 'none';
  paxBtn.setAttribute('aria-expanded', 'false');

  paxBtn.addEventListener('click', () => {
    const isHidden = paxPanel.style.display === 'none' || paxPanel.style.display === '';
    paxPanel.style.display = isHidden ? 'block' : 'none';
    paxBtn.setAttribute('aria-expanded', String(isHidden));
  }, { once: false });
}
document.addEventListener('DOMContentLoaded', initPassengersPanel);

/// Show "Welcome, First Last" in header on ALL pages
$(function () {
  $.getJSON('session_info.php')
    .done(function (data) {
      console.log('session_info.php data (header):', data);
      if (data.loggedIn) {
        $('#userNameDisplay').text('Welcome, ' + data.firstName + ' ' + data.lastName);
      } else {
        $('#userNameDisplay').text('');
      }
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      console.error('session_info.php ERROR:', textStatus, errorThrown);
      console.error('Response:', jqXHR.responseText);
    });
});

// Show who you are on the contact page (if that element exists)
$(function () {
  $.getJSON('session_info.php')
    .done(function (data) {
      const $el = $('#contactUserName');
      if (!$el.length) return; // not on contact page

      if (data.loggedIn) {
        $el.text(data.firstName + ' ' + data.lastName);
      } else {
        $el.text('Guest (please login)');
      }
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      console.error('session_info.php ERROR (contactUserName):', textStatus, errorThrown);
      console.error('Response:', jqXHR.responseText);
    });
});
console.log('Java.js end of file reached');