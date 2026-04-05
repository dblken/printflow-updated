const http = require('http');
const { Server } = require('socket.io');

const server = http.createServer();
const io = new Server(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

const users = new Map(); // key: role_userId, value: socketId

io.on('connection', (socket) => {
    socket.on('join', ({ userId, role }) => {
        const userKey = `${role}_${userId}`;
        users.set(userKey, socket.id);
        socket.userKey = userKey;
        console.log(`User joined: ${userKey} (${socket.id})`);
    });

    socket.on('call-user', ({ toUserId, toRole, signal, fromName, fromAvatar, type, orderId }) => {
        const toUserKey = `${toRole}_${toUserId}`;
        const toSocketId = users.get(toUserKey);

        if (toSocketId) {
            io.to(toSocketId).emit('incoming-call', {
                fromUserKey: socket.userKey,
                signal,
                fromName,
                fromAvatar,
                type,
                orderId
            });
            console.log(`Calling ${toUserKey} from ${socket.userKey}`);
        } else {
            socket.emit('call-failed', { message: 'User is offline' });
        }
    });

    socket.on('accept-call', ({ toUserKey, signal }) => {
        const toSocketId = users.get(toUserKey);
        if (toSocketId) {
            io.to(toSocketId).emit('call-accepted', { signal });
        }
    });

    socket.on('decline-call', ({ toUserKey, reason }) => {
        const toSocketId = users.get(toUserKey);
        if (toSocketId) {
            io.to(toSocketId).emit('call-declined', { reason });
        }
    });

    socket.on('end-call', ({ toUserKey }) => {
        const toSocketId = users.get(toUserKey);
        if (toSocketId) {
            io.to(toSocketId).emit('call-ended');
        }
    });

    socket.on('ice-candidate', ({ toUserKey, candidate }) => {
        const toSocketId = users.get(toUserKey);
        if (toSocketId) {
            io.to(toSocketId).emit('ice-candidate', { candidate });
        }
    });

    socket.on('disconnect', () => {
        if (socket.userKey) {
            users.delete(socket.userKey);
            console.log(`User disconnected: ${socket.userKey}`);
        }
    });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`Signaling server running on port ${PORT}`);
});
