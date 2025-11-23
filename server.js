const express = require('express');
const fs = require('fs').promises;
const path = require('path');
const cors = require('cors');
const bodyParser = require('body-parser');

const app = express();
app.use(cors());
app.use(bodyParser.json());

// Adjust this if your public files live elsewhere:
app.use(express.static(path.join(__dirname)));

const FLIGHTS_PATH = path.join(__dirname, 'flights.json');
const BACKUP_PATH  = path.join(__dirname, 'flights.db.backup.json');

// Utility: read flights array from file
async function readFlights() {
  const raw = await fs.readFile(FLIGHTS_PATH, 'utf8');
  const data = JSON.parse(raw);
  if (!Array.isArray(data)) throw new Error('flights.json must be a plain array');
  return data;
}

// Utility: write flights array back to file (with a quick backup)
async function writeFlights(flights) {
  await fs.writeFile(BACKUP_PATH, JSON.stringify(flights, null, 2)); // optional backup
  // atomic-ish write: write temp then rename
  const tmp = FLIGHTS_PATH + '.tmp';
  await fs.writeFile(tmp, JSON.stringify(flights, null, 2));
  await fs.rename(tmp, FLIGHTS_PATH);
}

// GET flights (for the client to load)
app.get('/api/flights', async (req, res) => {
  try {
    const flights = await readFlights();
    res.json(flights);
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to read flights' });
  }
});

/**
 * POST /api/book
 * Body:
 * {
 *   legs: [{ flightId, departDate }, ...],
 *   take: <total passengers to remove from each leg, integer >= 1>
 * }
 * Server decrements availableSeats for each matching leg and persists to flights.json.
 */
app.post('/api/book', async (req, res) => {
  try {
    const { legs, take } = req.body || {};
    if (!Array.isArray(legs) || !Number.isInteger(take) || take <= 0) {
      return res.status(400).json({ error: 'Invalid body' });
    }

    const flights = await readFlights();
    legs.forEach(({ flightId, departDate }) => {
      const idx = flights.findIndex(
        f => f.flightId === flightId && f.departDate === departDate
      );
      if (idx >= 0) {
        const current = Number(flights[idx].availableSeats) || 0;
        flights[idx].availableSeats = Math.max(0, current - take);
      }
    });

    await writeFlights(flights);
    res.json({ ok: true, flights }); // return new state if you want
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to update flights' });
  }
});

// Start server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`API running on http://localhost:${PORT}`);
});

const HOTELS_PATH = path.join(__dirname, 'hotels.xml');

app.post('/update_hotel', async (req, res) => {
  try {
    const { hotelId, newAvailableRooms } = req.body;
    if (!hotelId || !Number.isInteger(newAvailableRooms) || newAvailableRooms < 0) {
      return res.status(400).json({ error: 'Invalid body' });
    }

    // Read XML 
    const xmlStr = await fs.readFile(HOTELS_PATH, 'utf8');

    // Find all hotel blocks
    const hotelBlockRegex = /<hotel>[\s\S]*?<\/hotel>/gi;
    const blocks = xmlStr.match(hotelBlockRegex) || [];
    let matchedIndex = -1;
    for (let i = 0; i < blocks.length; i++) {
      const b = blocks[i];
      const idMatch = b.match(/<hotelId>([\s\S]*?)<\/hotelId>/i);
      const idVal = idMatch ? idMatch[1].trim() : null;
      if (idVal && idVal.toLowerCase() === String(hotelId).toLowerCase()) {
        matchedIndex = i;
        break;
      }
    }

    if (matchedIndex === -1) return res.status(404).json({ error: 'Hotel not found' });

    // Replace availableRooms value in that block (clearer two-capture form)
    const oldBlock = blocks[matchedIndex];
    const newBlock = oldBlock.replace(
      /(<availableRooms>)[\s\S]*?(<\/availableRooms>)/i,
      (match, openTag, closeTag) => `${openTag}${String(newAvailableRooms)}${closeTag}`
    );

    const updatedXmlStr = xmlStr.replace(oldBlock, newBlock);

    // Safety: write a timestamped backup of the original file before overwriting
    const backupPath = HOTELS_PATH + '.bak.' + Date.now();
    try {
      await fs.writeFile(backupPath, xmlStr, 'utf8');
      console.log('Created hotels.xml backup:', backupPath);
    } catch (bkErr) {
      console.warn('Failed to create backup for hotels.xml:', bkErr);
      // proceed anyway; we still attempt an atomic write
    }

    // Atomic-ish write: write to a temp file then rename
    const tmpPath = HOTELS_PATH + '.tmp';
    await fs.writeFile(tmpPath, updatedXmlStr, 'utf8');
    await fs.rename(tmpPath, HOTELS_PATH);

    return res.json({ ok: true, hotelId, newAvailableRooms, backup: backupPath, note: 'XML updated (atomic write)' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to update hotel' });
  }
});

const CARSPATH = path.join(__dirname, "cars.xml");

// async function readCarsXml() {
//   return await fs.readFile(CARSPATH, "utf8");
// }
// async function writeCarsXml(xmlStr) {
//   await fs.writeFile(CARSPATH, xmlStr, "utf8");
// }

app.post("/updatecar", async (req, res) => {
  const { carId, newAvailableCars } = req.body;
  if (!carId || !Number.isInteger(newAvailableCars)) {
    return res.status(400).json({ error: "Missing required parameters" });
  }

  try {
    let xmlData = await fs.readFile(CARSPATH, "utf8");

    // Get start/end of <Cars> to preserve comments/header if any
    const carsStart = xmlData.indexOf("<Cars>");
    const carsEnd = xmlData.lastIndexOf("</Cars>");
    const before = xmlData.slice(0, carsStart + "<Cars>".length);
    const after = xmlData.slice(carsEnd);

    // All <Car> blocks
    const carBlockRegex = /<Car>[\s\S]*?<\/Car>/g;
    const blocks = (xmlData.match(carBlockRegex) || []);
    let updated = false;

    for (let i = 0; i < blocks.length; i++) {
      const block = blocks[i];
      const idMatch = block.match(/<CarID>\s*((.|\n)*?)\s*<\/CarID>/);
      const idVal = idMatch ? idMatch[1].trim() : null;
      if (idVal === String(carId)) {
        // Only update availableCars for the matching CarID
        blocks[i] = block.replace(/(<availableCars>)[\s\S]*?(<\/availableCars>)/i, 
                                 `$1${String(newAvailableCars)}$2`);
        updated = true;
        break;
      }
    }

    if (!updated) return res.status(404).json({error: "Car not found"});

    // Reconstruct content, preserve formatting/other blocks
    const out = before + "\n" + blocks.join("\n") + "\n" + after;
    await fs.writeFile(CARSPATH, out, "utf8");
    res.json({ ok:true, carId, newAvailableCars });
  } catch (err) {
    console.error("Error updating car XML:", err);
    res.status(500).json({ error: "Internal server error" });
  }
});




