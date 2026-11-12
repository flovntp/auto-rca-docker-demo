// For hosts whose kernel predates getrandom() (Linux < 3.17, such as a
// Synology NAS on 3.10): git then fails with "unable to get random bytes".
// Loaded with LD_PRELOAD, this reads /dev/urandom instead.
#define _GNU_SOURCE
#include <sys/types.h>
#include <fcntl.h>
#include <unistd.h>
#include <errno.h>

ssize_t getrandom(void *buf, size_t len, unsigned int flags) {
    (void) flags;
    int fd = open("/dev/urandom", O_RDONLY | O_CLOEXEC);
    if (fd < 0) return -1;
    size_t n = 0;
    while (n < len) {
        ssize_t r = read(fd, (char *) buf + n, len - n);
        if (r < 0 && errno == EINTR) continue;
        if (r <= 0) { close(fd); return n ? (ssize_t) n : -1; }
        n += r;
    }
    close(fd);
    return (ssize_t) n;
}

int getentropy(void *buf, size_t len) {
    if (len > 256) { errno = EIO; return -1; }
    return getrandom(buf, len, 0) == (ssize_t) len ? 0 : -1;
}
