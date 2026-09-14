import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
        // SetCookie pose des cookies `secure` : jsdom les rejette silencieusement sur une URL http
        environmentOptions: { jsdom: { url: 'https://ladecadanse.test/' } }
    }
});
