/**
 * PrintFlow Socket.IO Server
 * Real-time communication server for chat and calls
 */

const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
const server = http.createServer(app);

// Socket.IO with CORS enabled
const io = new Server(server, {
    cors: {
        origin: '*',
        methods: ['GET', 'POST']
    }
});

// Store active users
const activeUsers = new Map();

io.on('connection', (socket) => {
    console.log('✅ User connected:', socket.id);

    // User joins with their info
    socket.on('user-join', (userData) => {
        activeUsers.set(socket.id, {
            userId: userData.userId,
            userName: userData.userName,
            role: userData.role,
            orderId: userData.orderId
        });
        console.log(`👤 ${userData.userName} (${userData.role}) joined`);
        
        // Notify others in the same order
        if (userData.orderId) {
            socket.join(`order-${userData.orderId}`);
            socket.to(`order-${userData.orderId}`).emit('user-online', {
                userId: userData.userId,
                userName: userData.userName
            });
        }
    });

    // Typing indicator
    socket.on('typing', (data) => {
        socket.to(`order-${data.orderId}`).emit('user-typing', {
            userId: data.userId,
            userName: data.userName
        });
    });

    socket.on('stop-typing', (data) => {
        socket.to(`order-${data.orderId}`).emit('user-stop-typing', {
            userId: data.userId
        });
    });

    // New message notification
    socket.on('new-message', (data) => {
        socket.to(`order-${data.orderId}`).emit('message-received', data);
    });

    // Call signaling
    socket.on('call-user', (data) => {
        console.log(`📞 Call initiated: ${data.from} → ${data.to}`);
        io.emit('incoming-call', data);
    });

    socket.on('call-accepted', (data) => {
        console.log(`✅ Call accepted: ${data.to}`);
        io.emit('call-accepted', data);
    });

    socket.on('call-rejected', (data) => {
        console.log(`❌ Call rejected: ${data.to}`);
        io.emit('call-rejected', data);
    });

    socket.on('call-ended', (data) => {
        console.log(`📴 Call ended`);
        io.emit('call-ended', data);
    });

    // WebRTC signaling
    socket.on('webrtc-offer', (data) => {
        io.emit('webrtc-offer', data);
    });

    socket.on('webrtc-answer', (data) => {
        io.emit('webrtc-answer', data);
    });

    socket.on('webrtc-ice-candidate', (data) => {
        io.emit('webrtc-ice-candidate', data);
    });

    // Disconnect
    socket.on('disconnect', () => {
        const user = activeUsers.get(socket.id);
        if (user) {
            console.log(`👋 ${user.userName} disconnected`);
            if (user.orderId) {
                socket.to(`order-${user.orderId}`).emit('user-offline', {
                    userId: user.userId
                });
            }
            activeUsers.delete(socket.id);
        } else {
            console.log('❌ User disconnected:', socket.id);
        }
    });
});

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({ 
        status: 'ok', 
        activeUsers: activeUsers.size,
        timestamp: new Date().toISOString()
    });
});

const PORT = 3000;
server.listen(PORT, () => {
    console.log('🚀 PrintFlow Socket.IO Server running on port', PORT);
    console.log('📡 Listening for connections...');
});
