# PrintFlow Socket.IO Server Setup Guide

## Quick Start (3 Steps)

### Step 1: Install Dependencies
Open Command Prompt or PowerShell in the PrintFlow directory:
```bash
cd C:\xampp\htdocs\printflow
npm install
```

### Step 2: Start the Socket.IO Server
```bash
node server.js
```

You should see:
```
🚀 PrintFlow Socket.IO Server running on port 3000
📡 Listening for connections...
```

### Step 3: Test the Connection
1. Keep the server running (don't close the terminal)
2. Open your browser to: http://localhost/printflow/customer/chat.php?order_id=2281
3. Open browser console (F12)
4. You should see: "Connected to signaling server"

## Troubleshooting

### Error: "Cannot find module 'express'"
**Solution:** Run `npm install` in the printflow directory

### Error: "Port 3000 is already in use"
**Solution:** 
- Close any other Node.js processes
- Or change port in server.js (line 48) and printflow_call.js (line 8)

### Error: "ERR_CONNECTION_REFUSED" still appears
**Solution:**
1. Make sure server.js is running (check terminal)
2. Verify you see "🚀 PrintFlow Socket.IO Server running on port 3000"
3. Check if port 3000 is blocked by firewall
4. Try restarting the server

### Frontend not connecting
**Solution:**
1. Clear browser cache (Ctrl+Shift+Delete)
2. Hard refresh the page (Ctrl+F5)
3. Check browser console for errors

## Running in Production

### Option 1: Keep terminal open
Just run `node server.js` and keep the terminal window open

### Option 2: Run as background process (Windows)
```bash
npm install -g pm2
pm2 start server.js --name printflow-socket
pm2 save
pm2 startup
```

### Option 3: Run on system startup (Windows)
Create a batch file `start-socket-server.bat`:
```batch
@echo off
cd C:\xampp\htdocs\printflow
node server.js
```

Add this batch file to Windows Startup folder:
- Press Win+R
- Type: shell:startup
- Copy the batch file there

## Testing the Connection

### Test 1: Health Check
Open browser to: http://localhost:3000/health

Should return:
```json
{
  "status": "ok",
  "activeUsers": 0,
  "timestamp": "2024-01-15T10:30:00.000Z"
}
```

### Test 2: Console Logs
When a user opens the chat page, server terminal should show:
```
✅ User connected: abc123xyz
👤 John Doe (Customer) joined
```

### Test 3: Real-time Features
1. Open chat in two different browsers
2. Type a message in one
3. Should appear instantly in the other

## Common Issues

### Issue: Server stops when I close terminal
**Solution:** Use pm2 (see Production section above)

### Issue: XAMPP and Node.js conflict
**Solution:** They run on different ports (XAMPP: 80, Node: 3000) - no conflict

### Issue: Socket disconnects frequently
**Solution:** 
- Check your internet connection
- Increase timeout in server.js if needed
- Check firewall settings

## File Structure
```
printflow/
├── server.js           ← Socket.IO server (NEW)
├── package.json        ← Dependencies (NEW)
├── node_modules/       ← Auto-created by npm install
└── public/
    └── assets/
        └── js/
            └── printflow_call.js  ← Frontend connection
```

## Support

If you still have issues:
1. Check server terminal for error messages
2. Check browser console (F12) for errors
3. Verify both XAMPP and Node.js server are running
4. Test the health endpoint: http://localhost:3000/health
