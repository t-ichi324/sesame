/*-------------------------------
SQLITE4
-------------------------------*/
CREATE TABLE `map`(
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `parents_id` INTEGER,
  `loc` TEXT,
  `is_publish` INTEGER,
  `tag` TEXT,
  `title` TEXT,
  `description` TEXT,
  `og_image` TEXT,
  `lastmod` TEXT,
  `changefreq` TEXT,
  `priority` TEXT
);
create unique index idx_loc on map(loc);
create index idx_tag on map(tag);
create index idx_title on map(title);