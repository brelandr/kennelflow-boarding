# Push KennelFlow Boarding

## GitHub

```bash
cd /Users/randy/wordpress-plugins/KennelPress/kennelflow-boarding
git push -u origin main
git push origin v0.2.6
gh release create v0.2.6 --title "KennelFlow Boarding 0.2.6" --notes-file .release/0.2.6.md
```

## WordPress.org

```bash
./deploy-to-wordpress-org.sh --commit
```

Last published on WordPress.org: **0.1.2**. This release publishes **0.2.6**.
