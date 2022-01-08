mtscan-rssi-plot
=======

Signal level plotting script for MTscan logs.

![Screenshot](/example/1616673600.png?raw=true)

Creates pixel-perfect graphs with time aggregation.

# Usage
```console
$ ./plot.php <file> <address> <aggregation>
```

- File is a MTscan log (.mtscan or .mtscan.gz).
- Address is the network BSSID (XX:XX:XX:XX:XX:XX or XXXXXXXXXXXX).
- Aggregation is a time bin in seconds.

Example:
```console
$ ./plot.php ./example/20210307V-213220.mtscan.gz 24:A4:3C:F4:5B:A7 60
```