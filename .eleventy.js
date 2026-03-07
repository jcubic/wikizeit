export default function(eleventyConfig) {

    // Passthrough copy for static assets — contents of src/static/ are copied to output root
    eleventyConfig.addPassthroughCopy({ "src/static": "/" });

    // Copy PHP backend, wrapper and .htaccess into output so _site/ is fully deployable
    eleventyConfig.addPassthroughCopy({ "api": "/api" });
    eleventyConfig.addPassthroughCopy({ "index.php": "/index.php" });
    eleventyConfig.addPassthroughCopy({ ".htaccess": "/.htaccess" });

    // Blog post collection sorted by date descending
    eleventyConfig.addCollection("post", function(collectionApi) {
        return collectionApi.getFilteredByGlob("src/blog/posts/*.md").sort((a, b) => {
            return b.date - a.date;
        });
    });

    // Unique tags list collection (excluding internal tags)
    eleventyConfig.addCollection("tagsList", function(collectionApi) {
        const tagsSet = new Set();
        collectionApi.getAll().forEach(item => {
            if (item.data.tags) {
                item.data.tags.forEach(tag => {
                    if (tag !== "post" && tag !== "all") {
                        tagsSet.add(tag);
                    }
                });
            }
        });
        return [...tagsSet].sort();
    });

    // Date formatting filter (Polish locale)
    eleventyConfig.addFilter("dateFormat", function(date, format) {
        const d = new Date(date);
        const months = [
            "stycznia", "lutego", "marca", "kwietnia", "maja", "czerwca",
            "lipca", "sierpnia", "września", "października", "listopada", "grudnia"
        ];
        if (format === "iso") {
            return d.toISOString().split("T")[0];
        }
        if (format === "short") {
            const day = d.getDate();
            const month = String(d.getMonth() + 1).padStart(2, "0");
            const year = d.getFullYear();
            return `${day}.${month}.${year}`;
        }
        // Default: "5 marca 2026"
        const day = d.getDate();
        const month = months[d.getMonth()];
        const year = d.getFullYear();
        return `${day} ${month} ${year}`;
    });

    // Reading time filter
    eleventyConfig.addFilter("readingTime", function(content) {
        if (!content) return "1 min";
        const words = content.split(/\s+/).length;
        const minutes = Math.ceil(words / 200);
        return `${minutes} min`;
    });

    // Excerpt filter — first paragraph or first N characters
    eleventyConfig.addFilter("excerpt", function(content, length) {
        if (!content) return "";
        const len = length || 200;
        // Strip HTML tags
        const text = content.replace(/<[^>]+>/g, "").trim();
        if (text.length <= len) return text;
        return text.substring(0, len).replace(/\s+\S*$/, "") + "...";
    });

    // Limit filter for arrays
    eleventyConfig.addFilter("limit", function(arr, limit) {
        if (!Array.isArray(arr)) return arr;
        return arr.slice(0, limit);
    });

    // JSON stringify filter for JSON-LD
    eleventyConfig.addFilter("jsonify", function(value) {
        return JSON.stringify(value, null, 2);
    });

    // Slugify filter
    eleventyConfig.addFilter("slugify", function(str) {
        return str
            .toString()
            .toLowerCase()
            .trim()
            .replace(/\s+/g, "-")
            .replace(/[^\w-]+/g, "")
            .replace(/--+/g, "-")
            .replace(/^-+/, "")
            .replace(/-+$/, "");
    });

    return {
        dir: {
            input: "src",
            output: "_site",
            includes: "_includes",
            data: "_data"
        },
        templateFormats: ["liquid", "md", "html"],
        htmlTemplateEngine: "liquid",
        markdownTemplateEngine: "liquid",
        pathPrefix: "/wikizeit/"
    };
};
