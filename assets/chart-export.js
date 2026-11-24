/**
 * Chart export: renders a clean copy of a Chart.js line chart off-screen and
 * downloads it as a JPEG at a fixed print size (inches at a given DPI).
 *
 * The on-screen chart is never touched. The copy shows only the "Team Average"
 * line, with no legend, tooltip or page titles, on a white background.
 */
(function (global) {
    'use strict';

    // Chart.js sizes fonts, lines and points in CSS pixels. Laying the copy
    // out at this width (height follows the aspect ratio) keeps the text in the
    // same proportion as the on-screen chart; it is then scaled up to the
    // exact print pixel size.
    var LAYOUT_CSS_WIDTH = 620;

    // JPEG has no transparency: paint the background white.
    var WHITE_BACKGROUND = {
        id: 'whiteBackground',
        beforeDraw: function (c) {
            var ctx = c.ctx;
            ctx.save();
            ctx.globalCompositeOperation = 'destination-over';
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, c.width, c.height);
            ctx.restore();
        }
    };

    function findAverageDataset(chart) {
        var sets = chart.data.datasets;
        for (var i = 0; i < sets.length; i++) {
            if (sets[i].label === 'Team Average') return sets[i];
        }
        return sets[sets.length - 1];
    }

    // Copy the on-screen chart's styling, minus the legend and tooltip.
    function exportConfig(chart, pixelRatio) {
        var src = chart.config.options;
        var yScale = src.scales.y;
        var xScale = src.scales.x;
        var avg = findAverageDataset(chart);
        var dataset = Object.assign({}, avg, { hidden: false });

        return {
            type: 'line',
            data: {
                labels: chart.data.labels.slice(),
                datasets: [dataset]
            },
            options: {
                responsive: false,
                animation: false,
                devicePixelRatio: pixelRatio,
                events: [],
                layout: { padding: { left: 18, right: 12, top: 8, bottom: 4 } },
                plugins: {
                    legend: { display: false },
                    title: { display: false },
                    tooltip: { enabled: false }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: yScale.min,
                        max: yScale.max,
                        // Show every whole number, as on screen; never skip any.
                        ticks: { stepSize: 1, autoSkip: false, font: { size: 12, family: "'Poppins', sans-serif" } },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        title: {
                            display: true,
                            text: yScale.title && yScale.title.text,
                            font: { size: 14, family: "'Poppins', sans-serif" }
                        }
                    },
                    x: {
                        ticks: {
                            font: { size: 11, family: "'Poppins', sans-serif" },
                            maxRotation: 45,
                            minRotation: 45
                        },
                        grid: { display: false }
                    }
                }
            },
            plugins: [WHITE_BACKGROUND]
        };
    }

    // Deep-copy plain option/data values, dropping functions (click handlers,
    // tooltip callbacks) that the static copy does not need.
    function plainCopy(value) {
        if (typeof value === 'function') return undefined;
        if (Array.isArray(value)) return value.map(plainCopy);
        if (value && typeof value === 'object') {
            var out = {};
            Object.keys(value).forEach(function (k) {
                if (k.charAt(0) === '_' || k.charAt(0) === '$') return;
                var v = plainCopy(value[k]);
                if (v !== undefined) out[k] = v;
            });
            return out;
        }
        return value;
    }

    // Copy the whole on-screen chart as it is, with titles and tooltip off.
    // opts.legend: keep the key (true) or hide it (false).
    function cloneConfig(chart, pixelRatio, opts) {
        var options = plainCopy(chart.config.options) || {};
        options.responsive = false;
        options.maintainAspectRatio = false;
        options.animation = false;
        options.devicePixelRatio = pixelRatio;
        options.events = [];
        // A small margin so labels never touch the image edge.
        options.layout = { padding: { left: 10, right: 12, top: 10, bottom: 6 } };
        options.plugins = options.plugins || {};
        options.plugins.title = { display: false };
        options.plugins.subtitle = { display: false };
        options.plugins.tooltip = { enabled: false };
        options.plugins.legend = options.plugins.legend || {};
        options.plugins.legend.display = !!opts.legend;

        return {
            type: chart.config.type,
            data: {
                labels: chart.data.labels.slice(),
                datasets: chart.data.datasets.map(function (ds, i) {
                    // Keep whatever is hidden on screen hidden in the copy too.
                    return Object.assign(plainCopy(ds), { hidden: !chart.isDatasetVisible(i) });
                })
            },
            options: options,
            plugins: [WHITE_BACKGROUND]
        };
    }

    // Write the DPI into the JPEG's JFIF header so Word/PowerPoint place the
    // image at its intended physical size.
    function setJpegDpi(bytes, dpi) {
        var isJfif = bytes[2] === 0xFF && bytes[3] === 0xE0 &&
            bytes[6] === 0x4A && bytes[7] === 0x46 && bytes[8] === 0x49 &&
            bytes[9] === 0x46 && bytes[10] === 0x00;
        if (isJfif) {
            bytes[13] = 1; // units: dots per inch
            bytes[14] = dpi >> 8; bytes[15] = dpi & 0xFF;
            bytes[16] = dpi >> 8; bytes[17] = dpi & 0xFF;
            return bytes;
        }
        // No JFIF segment: insert one straight after the SOI marker.
        var app0 = [0xFF, 0xE0, 0x00, 0x10, 0x4A, 0x46, 0x49, 0x46, 0x00,
            0x01, 0x01, 0x01, dpi >> 8, dpi & 0xFF, dpi >> 8, dpi & 0xFF, 0x00, 0x00];
        var out = new Uint8Array(bytes.length + app0.length);
        out.set(bytes.subarray(0, 2), 0);
        out.set(app0, 2);
        out.set(bytes.subarray(2), 2 + app0.length);
        return out;
    }

    /**
     * Render `chart` as a JPEG Blob of exactly widthIn x heightIn at `dpi`.
     * opts (optional):
     *   mode: 'average' (default) = team-average line only, no legend;
     *         'clone' = the chart as shown on screen, minus tooltip/titles.
     *   legend: for 'clone', keep the key.
     *   layoutWidth: CSS px width to lay the copy out at (sets text size
     *         relative to the image; default LAYOUT_CSS_WIDTH).
     * Resolves to a Blob.
     */
    function renderChartJpeg(chart, widthIn, heightIn, dpi, opts) {
        opts = opts || {};
        var pxW = Math.round(widthIn * dpi);
        var pxH = Math.round(heightIn * dpi);
        var cssW = opts.layoutWidth || LAYOUT_CSS_WIDTH;
        var cssH = Math.round(cssW * heightIn / widthIn);
        var ratio = pxW / cssW;

        var host = document.createElement('div');
        host.style.cssText = 'position:fixed;left:-10000px;top:0;width:' + cssW +
            'px;height:' + cssH + 'px;overflow:hidden;';
        var canvas = document.createElement('canvas');
        canvas.width = cssW;
        canvas.height = cssH;
        canvas.style.width = cssW + 'px';
        canvas.style.height = cssH + 'px';
        host.appendChild(canvas);
        document.body.appendChild(host);

        var ready = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
        return ready.then(function () {
            var config = opts.mode === 'clone'
                ? cloneConfig(chart, ratio, opts)
                : exportConfig(chart, ratio);
            var copy = new global.Chart(canvas, config);

            // Resample onto a canvas of the exact pixel size, on white.
            var out = document.createElement('canvas');
            out.width = pxW;
            out.height = pxH;
            var octx = out.getContext('2d');
            octx.fillStyle = '#ffffff';
            octx.fillRect(0, 0, pxW, pxH);
            octx.imageSmoothingQuality = 'high';
            octx.drawImage(canvas, 0, 0, pxW, pxH);

            copy.destroy();
            host.remove();

            return new Promise(function (resolve, reject) {
                out.toBlob(function (blob) {
                    if (!blob) { reject(new Error('JPEG encoding failed')); return; }
                    blob.arrayBuffer().then(function (buf) {
                        var bytes = setJpegDpi(new Uint8Array(buf), dpi);
                        resolve(new Blob([bytes], { type: 'image/jpeg' }));
                    }, reject);
                }, 'image/jpeg', 0.95);
            });
        });
    }

    function downloadBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 10000);
    }

    /**
     * items: [{ chart, filename, options? }] (options as for renderChartJpeg).
     * Renders and downloads each in turn.
     */
    function downloadChartsAsJpeg(items, widthIn, heightIn, dpi) {
        return items.reduce(function (p, item) {
            return p.then(function () {
                return renderChartJpeg(item.chart, widthIn, heightIn, dpi, item.options).then(function (blob) {
                    downloadBlob(blob, item.filename);
                    // A short gap so the browser treats them as separate downloads.
                    return new Promise(function (r) { setTimeout(r, 400); });
                });
            });
        }, Promise.resolve());
    }

    global.PulseChartExport = {
        renderChartJpeg: renderChartJpeg,
        downloadChartsAsJpeg: downloadChartsAsJpeg,
        setJpegDpi: setJpegDpi
    };
})(window);
