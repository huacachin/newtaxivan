@once
    @push('styles')
    <style>
        .lightbox-overlay {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,.85);
            display: flex; align-items: center; justify-content: center;
        }
        .lightbox-img {
            max-width: 90vw; max-height: 85vh;
            border-radius: 6px;
            box-shadow: 0 4px 24px rgba(0,0,0,.5);
        }
        .lightbox-btn {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,.15); border: none; color: #fff;
            font-size: 28px; padding: 8px 14px; border-radius: 50%;
            cursor: pointer; transition: background .2s;
        }
        .lightbox-btn:hover { background: rgba(255,255,255,.3); }
        .lightbox-prev { left: 16px; }
        .lightbox-next { right: 16px; }
        .lightbox-close {
            position: absolute; top: 16px; right: 20px;
            background: none; border: none; color: #fff;
            font-size: 32px; cursor: pointer; line-height: 1;
        }
        .lightbox-counter {
            position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%);
            color: #fff; font-size: 14px; background: rgba(0,0,0,.5);
            padding: 4px 12px; border-radius: 12px;
        }
    </style>
    @endpush

    <div x-data="{
            open: false,
            images: [],
            current: 0,
            show(imgs, idx) { this.images = imgs; this.current = idx; this.open = true; },
            close() { this.open = false; this.images = []; this.current = 0; },
            prev() { this.current = (this.current - 1 + this.images.length) % this.images.length; },
            next() { this.current = (this.current + 1) % this.images.length; },
         }"
         x-on:open-lightbox.window="show($event.detail.images, $event.detail.index)"
         x-on:keydown.escape.window="close()"
         x-on:keydown.left.window="if(open) prev()"
         x-on:keydown.right.window="if(open) next()"
         x-show="open"
         x-cloak
         class="lightbox-overlay"
         x-on:click.self="close()">
        <template x-if="open && images.length">
            <div>
                <img :src="images[current]" class="lightbox-img" alt="Imagen">
                <button class="lightbox-close" x-on:click="close()" title="Cerrar">&times;</button>
                <template x-if="images.length > 1">
                    <div>
                        <button class="lightbox-btn lightbox-prev" x-on:click.stop="prev()">&#8249;</button>
                        <button class="lightbox-btn lightbox-next" x-on:click.stop="next()">&#8250;</button>
                        <div class="lightbox-counter" x-text="(current + 1) + ' / ' + images.length"></div>
                    </div>
                </template>
            </div>
        </template>
    </div>
@endonce
